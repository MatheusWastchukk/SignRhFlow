<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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

        $signingUrl = data_get($payload, 'data.createDocument.signatures.0.link.short_link');
        if (is_string($signingUrl) && $signingUrl !== '' && ! str_starts_with($signingUrl, 'http')) {
            $signingUrl = 'https://'.$signingUrl;
        }

        return [
            'document_id' => $documentId,
            'signing_url' => is_string($signingUrl) && $signingUrl !== '' ? $signingUrl : null,
        ];
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
      link {
        short_link
      }
    }
  }
}
GRAPHQL,
            'variables' => [
                'document' => [
                    'name' => "Contrato {$contract->id}",
                ],
                'signers' => [$signer],
                'file' => null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $operations
     * @param array<string, mixed> $map
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
     * @param array<string, mixed> $payload
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
     * @param array<string, mixed> $payload
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
}
