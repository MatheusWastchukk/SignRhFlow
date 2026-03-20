<?php

namespace App\Http\Controllers;

use App\Support\Metrics;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MetricsController extends Controller
{
    #[OA\Get(
        path: '/api/metrics',
        operationId: 'metricsSnapshot',
        summary: 'Contadores simples (requer METRICS_TOKEN)',
        description: 'Rota habilitada apenas quando METRICS_TOKEN está definido no .env. Envie Authorization: Bearer <token>.',
        tags: ['Operations'],
        security: [['metricsBearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Snapshot dos contadores',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'counters', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer')),
                        new OA\Property(property: 'app', type: 'string', example: 'SignRhFlow API'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 403, description: 'Token inválido'),
            new OA\Response(response: 404, description: 'Métricas desligadas (sem METRICS_TOKEN)'),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'counters' => Metrics::snapshot(),
            'app' => config('app.name'),
        ]);
    }
}
