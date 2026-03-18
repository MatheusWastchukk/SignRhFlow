<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WebhookController extends Controller
{
    #[OA\Post(
        path: '/api/webhooks/autentique',
        operationId: 'autentiqueWebhook',
        summary: 'Recebe webhook da Autentique',
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook recebido'),
        ]
    )]
    public function autentique(Request $request): JsonResponse
    {
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

        if ($webhookLog->wasRecentlyCreated) {
            ProcessWebhookJob::dispatch($webhookLog->id);
        }

        return response()->json([
            'received' => true,
            'duplicate' => ! $webhookLog->wasRecentlyCreated,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
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
     * @param array<string, mixed> $payload
     */
    private function resolveDocumentId(array $payload): string
    {
        $documentId = (string) data_get($payload, 'data.id', data_get($payload, 'document.id', data_get($payload, 'documento.uuid', '')));

        return $documentId !== '' ? $documentId : 'unknown';
    }
}
