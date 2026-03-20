<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
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
