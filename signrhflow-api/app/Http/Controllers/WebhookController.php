<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookLog;
use App\Services\AutentiqueWebhookVerifier;
use App\Support\Metrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class WebhookController extends Controller
{
    #[OA\Post(
        path: '/api/webhooks/autentique',
        operationId: 'autentiqueWebhook',
        summary: 'Recebe webhook da Autentique',
        description: 'Idempotencia por hash SHA-256 do JSON. Payloads legados (documento.uuid, partes) sao mapeados. Com AUTENTIQUE_WEBHOOK_SECRET, exige header X-Autentique-Signature = HMAC-SHA256(hex) do corpo bruto.',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(
                name: 'X-Autentique-Signature',
                description: 'HMAC-SHA256(hex) do corpo HTTP bruto com o secret do webhook (obrigatorio se AUTENTIQUE_WEBHOOK_SECRET estiver definido)',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'event_type' => 'document.signed',
                    'data' => ['id' => 'uuid-do-documento-na-autentique'],
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook aceito',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'received', type: 'boolean', example: true),
                        new OA\Property(property: 'duplicate', type: 'boolean', example: false),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 403, description: 'Assinatura HMAC invalida ou ausente'),
        ]
    )]
    public function autentique(Request $request, AutentiqueWebhookVerifier $verifier): JsonResponse
    {
        if (! $verifier->verify($request)) {
            Log::warning('webhook.autentique.signature_invalid', [
                'has_header' => $request->hasHeader(AutentiqueWebhookVerifier::SIGNATURE_HEADER),
            ]);

            return response()->json(['message' => 'Assinatura do webhook invalida.'], 403);
        }

        $payload = $request->all();
        $eventType = $this->resolveEventType($payload);
        $documentId = $this->resolveDocumentId($payload);
        $eventHash = hash('sha256', json_encode($payload));

        $webhookLog = WebhookLog::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'autentique_document_id' => $documentId,
                'event_type' => $eventType,
                'payload' => $payload,
                'processed' => false,
            ]
        );

        $duplicate = ! $webhookLog->wasRecentlyCreated;

        if ($webhookLog->wasRecentlyCreated) {
            ProcessWebhookJob::dispatch($webhookLog->id);
        }

        Log::info('webhook.autentique.received', [
            'event_type' => $eventType,
            'autentique_document_id' => $documentId,
            'duplicate' => $duplicate,
        ]);

        Metrics::increment('webhook_received_total');
        if ($duplicate) {
            Metrics::increment('webhook_duplicate_total');
        }

        return response()->json([
            'received' => true,
            'duplicate' => $duplicate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEventType(array $payload): string
    {
        $eventType = (string) data_get($payload, 'event_type', data_get($payload, 'event', ''));
        if ($eventType !== '') {
            return $eventType;
        }

        if (data_get($payload, 'partes.0.assinado.created') !== null) {
            return 'signed';
        }
        if (data_get($payload, 'partes.0.recusado.created') !== null || data_get($payload, 'partes.0.rejeitado.created') !== null) {
            return 'rejected';
        }
        if (data_get($payload, 'partes.0.visualizado.created') !== null || data_get($payload, 'partes.0.mail.sent') !== null) {
            return 'pending';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDocumentId(array $payload): string
    {
        $documentId = (string) data_get($payload, 'data.id', data_get($payload, 'document.id', data_get($payload, 'documento.uuid', '')));

        return $documentId !== '' ? $documentId : 'unknown';
    }
}
