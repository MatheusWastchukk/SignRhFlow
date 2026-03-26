<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AutentiqueService
{
    /**
     * @return array{document_id: string, signing_url: string|null}
     */
    public function createDocument(Contract $contract): array
    {
        $token = (string) config('services.autentique.token');
        $url = (string) config('services.autentique.graphql_url');

        if ($token === '') {
            throw new RuntimeException('AUTENTIQUE_API_TOKEN nao configurado.');
        }

        $absolutePath = Storage::disk('local')->path($contract->file_path);

        if (! is_file($absolutePath)) {
            throw new RuntimeException('Arquivo do contrato nao encontrado para envio.');
        }

        $map = ['0' => ['variables.file']];

        $operationsWithLive = $this->buildCreateDocumentOperations($contract, true);
        $response = $this->sendCreateDocumentRequest($token, $url, $operationsWithLive, $map, $absolutePath);
        $payload = $response->json();
        $documentId = data_get($payload, 'data.createDocument.id');

        if ((! is_string($documentId) || $documentId === '') && $this->hasUnavailableVerificationCredits($payload)) {
            $operationsWithoutLive = $this->buildCreateDocumentOperations($contract, false);
            $response = $this->sendCreateDocumentRequest($token, $url, $operationsWithoutLive, $map, $absolutePath);
            $payload = $response->json();
            $documentId = data_get($payload, 'data.createDocument.id');
        }

        if (! is_string($documentId) || $documentId === '') {
            throw new RuntimeException("Resposta da Autentique sem document_id: {$this->payloadSnippet($payload)}");
        }

        $documentId = mb_strtolower(trim($documentId));

        if (is_array($payload['errors'] ?? null) && $payload['errors'] !== []) {
            Log::warning('Autentique createDocument retornou erros GraphQL junto com dados.', [
                'errors' => $payload['errors'],
                'document_id' => $documentId,
            ]);
        }

        $signatures = data_get($payload, 'data.createDocument.signatures');
        $signingUrl = $this->resolveSigningUrlFromSignaturesPayload(
            is_array($signatures) ? $signatures : [],
            $contract->employee?->email
        );

        if ($signingUrl === null) {
            $signingUrl = $this->resolveSigningUrlByDocumentId($documentId, $contract->employee?->email);
        }

        if ($signingUrl === null) {
            Log::warning('Nao foi possivel resolver link de assinatura Autentique apos createDocument.', [
                'contract_id' => $contract->id,
                'document_id' => $documentId,
                'signatures_count' => is_array($signatures) ? count($signatures) : 0,
            ]);
        }

        return [
            'document_id' => $documentId,
            'signing_url' => $signingUrl,
        ];
    }

    public function resolveSigningUrlByDocumentId(string $documentId, ?string $preferSignerEmail = null): ?string
    {
        $documentId = mb_strtolower(trim($documentId));
        if ($documentId === '') {
            return null;
        }

        $json = $this->graphQlJsonRequest(
            <<<'GRAPHQL'
query DocumentSigningLinks($id: UUID!) {
  document(id: $id) {
    signatures {
      public_id
      email
      link {
        short_link
      }
    }
  }
}
GRAPHQL,
            ['id' => $documentId]
        );

        if ($json !== null) {
            $this->logGraphQlErrors($json, 'document');

            $signatures = data_get($json, 'data.document.signatures');
            if (is_array($signatures) && $signatures !== []) {
                $resolved = $this->resolveSigningUrlFromSignaturesPayload($signatures, $preferSignerEmail);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return $this->panelAssinarSigningUrl($documentId);
    }

    /**
     * Mesmo padrao do e-mail da Autentique: {panel}/assinar/{documento_id}
     */
    /**
     * Atualiza status do contrato conforme progresso do documento na Autentique (assinaturas concluídas / rejeitadas).
     * Não altera contratos já SIGNED ou REJECTED.
     */
    public function applyDocumentProgressToContract(Contract $contract): void
    {
        if (in_array($contract->status, [Contract::STATUS_SIGNED, Contract::STATUS_REJECTED], true)) {
            return;
        }

        $documentId = $contract->autentique_document_id;
        if (! is_string($documentId) || trim($documentId) === '') {
            return;
        }

        $documentId = mb_strtolower(trim($documentId));

        $json = $this->graphQlJsonRequest(
            <<<'GRAPHQL'
query DocumentProgress($id: UUID!) {
  document(id: $id) {
    signatures_count
    signed_count
    rejected_count
  }
}
GRAPHQL,
            ['id' => $documentId]
        );

        if ($json === null) {
            return;
        }

        $this->logGraphQlErrors($json, 'documentProgress');

        $doc = data_get($json, 'data.document');
        if (! is_array($doc)) {
            return;
        }

        $signaturesCount = (int) ($doc['signatures_count'] ?? $doc['signaturesCount'] ?? 0);
        $signedCount = (int) ($doc['signed_count'] ?? $doc['signedCount'] ?? 0);
        $rejectedCount = (int) ($doc['rejected_count'] ?? $doc['rejectedCount'] ?? 0);

        if ($signaturesCount > 0 && $signedCount >= $signaturesCount) {
            $payload = ['status' => Contract::STATUS_SIGNED];
            if ($contract->signed_at === null) {
                $payload['signed_at'] = now();
            }
            $contract->forceFill($payload)->save();

            return;
        }

        if ($rejectedCount > 0) {
            $contract->forceFill([
                'status' => Contract::STATUS_REJECTED,
            ])->save();
        }
    }

    public function panelAssinarSigningUrl(string $documentId): ?string
    {
        $documentId = mb_strtolower(trim($documentId));
        if ($documentId === '' || $documentId === 'unknown') {
            return null;
        }

        if (! preg_match('/^[a-f0-9\-]{8,128}$/', $documentId)) {
            return null;
        }

        $base = rtrim((string) config('services.autentique.panel_base_url', 'https://painel.autentique.com.br'), '/');

        return $base.'/assinar/'.$documentId;
    }

    /**
     * @param  list<array<string, mixed>>  $signatures
     */
    private function resolveSigningUrlFromSignaturesPayload(array $signatures, ?string $preferSignerEmail): ?string
    {
        $ordered = $this->orderSignaturesByEmailPreference($signatures, $preferSignerEmail);

        foreach ($ordered as $signature) {
            if (! is_array($signature)) {
                continue;
            }

            $direct = $this->normalizeUrl(data_get($signature, 'link.short_link'));
            if ($direct !== null) {
                return $direct;
            }
        }

        foreach ($ordered as $signature) {
            if (! is_array($signature)) {
                continue;
            }

            $publicId = $this->signaturePublicId($signature);
            if ($publicId === null) {
                continue;
            }

            $fromMutation = $this->createLinkToSignature($publicId);
            if ($fromMutation !== null) {
                return $fromMutation;
            }
        }

        return null;
    }

    public function createLinkToSignature(string $publicId): ?string
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        foreach (['UUID!', 'String!', 'ID!'] as $graphqlScalar) {
            $query = <<<GRAPHQL
mutation CreateLinkToSignature(\$public_id: {$graphqlScalar}) {
  createLinkToSignature(public_id: \$public_id) {
    short_link
  }
}
GRAPHQL;

            $json = $this->graphQlJsonRequest($query, ['public_id' => $publicId]);

            if ($json === null) {
                continue;
            }

            $this->logGraphQlErrors($json, 'createLinkToSignature');

            $url = $this->normalizeUrl(data_get($json, 'data.createLinkToSignature.short_link'));
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function graphQlJsonRequest(string $query, array $variables = []): ?array
    {
        $token = (string) config('services.autentique.token');
        $url = (string) config('services.autentique.graphql_url');

        if ($token === '' || $url === '') {
            return null;
        }

        $body = ['query' => $query];
        if ($variables !== []) {
            $body['variables'] = $variables;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->acceptJson()
                ->post($url, $body);
        } catch (ConnectionException $exception) {
            Log::warning('Falha de conexao com Autentique (GraphQL JSON).', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Autentique GraphQL JSON retornou HTTP nao-sucesso.', [
                'status' => $response->status(),
                'body' => mb_substr(trim((string) $response->body()), 0, 800),
            ]);

            return null;
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  list<array<string, mixed>>  $signatures
     * @return list<array<string, mixed>>
     */
    private function orderSignaturesByEmailPreference(array $signatures, ?string $preferSignerEmail): array
    {
        $prefer = $preferSignerEmail !== null && $preferSignerEmail !== ''
            ? mb_strtolower(trim($preferSignerEmail))
            : null;

        if ($prefer === null) {
            return $signatures;
        }

        $match = [];
        $rest = [];

        foreach ($signatures as $signature) {
            if (! is_array($signature)) {
                continue;
            }

            $sigEmail = $this->signatureSignerEmail($signature);

            if ($sigEmail !== '' && $sigEmail === $prefer) {
                $match[] = $signature;
            } else {
                $rest[] = $signature;
            }
        }

        return array_merge($match, $rest);
    }

    /**
     * @param  array<string, mixed>  $signature
     */
    private function signatureSignerEmail(array $signature): string
    {
        $email = data_get($signature, 'email');
        if (is_string($email) && trim($email) !== '') {
            return mb_strtolower(trim($email));
        }

        $userEmail = data_get($signature, 'user.email');
        if (is_string($userEmail) && trim($userEmail) !== '') {
            return mb_strtolower(trim($userEmail));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $signature
     */
    private function signaturePublicId(array $signature): ?string
    {
        foreach (['public_id', 'publicId'] as $key) {
            $value = data_get($signature, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logGraphQlErrors(array $payload, string $context): void
    {
        $errors = $payload['errors'] ?? null;
        if (! is_array($errors) || $errors === []) {
            return;
        }

        Log::warning("Autentique GraphQL retornou erros ({$context}).", [
            'errors' => $errors,
        ]);
    }

    /**
     * Titulo exibido no painel da Autentique (evita apenas UUID cru no nome do documento).
     */
    private function buildDocumentDisplayName(Contract $contract): string
    {
        $employeeName = trim((string) $contract->employee?->name);
        if ($employeeName !== '') {
            $safe = mb_substr($employeeName, 0, 100);

            return 'Admissão - '.$safe;
        }

        $id = (string) $contract->id;
        if (strlen($id) > 13) {
            return 'Contrato '.mb_substr($id, 0, 8).'…';
        }

        return 'Contrato '.$id;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateDocumentOperations(Contract $contract, bool $withLiveVerification): array
    {
        $signer = [
            'email' => (string) $contract->employee?->email,
            'name' => (string) $contract->employee?->name,
            'action' => 'SIGN',
        ];

        if ($withLiveVerification) {
            $signer['security_verifications'] = [[
                'type' => 'LIVE',
            ]];
        }

        return [
            'query' => <<<'GRAPHQL'
mutation CreateDocument($document: DocumentInput!, $signers: [SignerInput!]!, $file: Upload!) {
  createDocument(document: $document, signers: $signers, file: $file) {
    id
    signatures {
      public_id
      email
      link {
        short_link
      }
    }
  }
}
GRAPHQL,
            'variables' => [
                'document' => [
                    'name' => $this->buildDocumentDisplayName($contract),
                ],
                'signers' => [$signer],
                'file' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $operations
     * @param  array<string, mixed>  $map
     */
    private function sendCreateDocumentRequest(string $token, string $url, array $operations, array $map, string $absolutePath): Response
    {
        $stream = fopen($absolutePath, 'r');

        if ($stream === false) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo para envio.');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->acceptJson()
                ->asMultipart()
                ->post($url, [
                    [
                        'name' => 'operations',
                        'contents' => json_encode($operations, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'name' => 'map',
                        'contents' => json_encode($map, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'name' => '0',
                        'contents' => $stream,
                        'filename' => basename($absolutePath),
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Falha de conexao com a Autentique.', 0, $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            $responseBody = trim((string) $response->body());
            $responseBody = $responseBody === '' ? 'sem corpo' : $responseBody;
            $maxLength = 1200;
            if (strlen($responseBody) > $maxLength) {
                $responseBody = substr($responseBody, 0, $maxLength).'...';
            }

            throw new RuntimeException("Autentique retornou HTTP {$response->status()}: {$responseBody}");
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasUnavailableVerificationCredits(array $payload): bool
    {
        $errors = data_get($payload, 'errors', []);
        if (! is_array($errors)) {
            return false;
        }

        $messages = array_map(static function ($error): string {
            if (! is_array($error)) {
                return '';
            }

            return mb_strtolower((string) data_get($error, 'message', ''));
        }, $errors);

        return in_array('unavailable_verifications_credits', $messages, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadSnippet(array $payload): string
    {
        $payloadSnippet = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($payloadSnippet) || $payloadSnippet === '') {
            return 'sem payload parseavel';
        }
        if (strlen($payloadSnippet) > 1200) {
            return substr($payloadSnippet, 0, 1200).'...';
        }

        return $payloadSnippet;
    }

    private function normalizeUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! str_starts_with($url, 'http')) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
