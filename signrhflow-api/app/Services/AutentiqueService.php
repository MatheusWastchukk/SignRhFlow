<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AutentiqueService
{
    /**
     * @return array{document_id: string}
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

        $operations = [
            'query' => <<<'GRAPHQL'
mutation CreateDocument($document: DocumentInput!, $file: Upload!) {
  createDocument(document: $document, file: $file) {
    id
  }
}
GRAPHQL,
            'variables' => [
                'document' => [
                    'name' => "Contrato {$contract->id}",
                    'signers' => [[
                        'email' => (string) $contract->employee?->email,
                        'name' => (string) $contract->employee?->name,
                        'action' => 'SIGN',
                        'security_verifications' => [[
                            'type' => 'LIVE',
                        ]],
                    ]],
                ],
                'file' => null,
            ],
        ];

        $map = ['0' => ['variables.file']];

        $stream = fopen($absolutePath, 'r');

        if ($stream === false) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo para envio.');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->acceptJson()
                ->withOptions([
                    'multipart' => [
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
                    ],
                ])
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Falha de conexao com a Autentique.', 0, $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException("Autentique retornou HTTP {$response->status()}.");
        }

        $payload = $response->json();
        $documentId = data_get($payload, 'data.createDocument.id');

        if (! is_string($documentId) || $documentId === '') {
            throw new RuntimeException('Resposta da Autentique sem document_id.');
        }

        return ['document_id' => $documentId];
    }
}
