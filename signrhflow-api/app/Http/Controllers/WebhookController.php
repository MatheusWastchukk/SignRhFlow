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
        $eventType = (string) data_get($payload, 'event_type', data_get($payload, 'event', 'unknown'));
        $documentId = (string) data_get($payload, 'data.id', data_get($payload, 'document.id', 'unknown'));
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
}
