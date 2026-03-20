<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    #[OA\Get(
        path: '/api/health',
        operationId: 'apiHealth',
        summary: 'Readiness (PostgreSQL + Redis)',
        description: 'Retorna 503 se alguma dependencia critica falhar. Liveness leve em GET /up.',
        tags: ['Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dependencias OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(
                            property: 'checks',
                            properties: [
                                new OA\Property(property: 'database', type: 'string', example: 'ok'),
                                new OA\Property(property: 'redis', type: 'string', example: 'ok'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 503, description: 'Dependencia indisponivel'),
        ]
    )]
    /**
     * Readiness: verifica dependências críticas (PostgreSQL + Redis).
     * Use em orquestradores/Docker; liveness simples continua em GET /up.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $allOk = ! in_array(false, $checks, true);
        $status = $allOk ? 'ok' : 'unhealthy';

        return response()->json([
            'status' => $status,
            'checks' => array_map(fn (bool $ok) => $ok ? 'ok' : 'fail', $checks),
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $pong = Redis::connection()->ping();

            return $pong === true || $pong === 'PONG' || $pong === '+PONG';
        } catch (\Throwable) {
            return false;
        }
    }
}
