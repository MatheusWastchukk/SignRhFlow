<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_readiness_returns_ok_when_database_and_redis_are_up(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('ping')->once()->andReturn('PONG');

        Redis::shouldReceive('connection')->once()->withNoArgs()->andReturn($connection);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                ],
            ]);
    }

    public function test_readiness_returns_503_when_redis_ping_fails(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('ping')->once()->andThrow(new \RuntimeException('Redis indisponível'));

        Redis::shouldReceive('connection')->once()->withNoArgs()->andReturn($connection);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'checks' => [
                    'database' => 'ok',
                    'redis' => 'fail',
                ],
            ]);
    }

    public function test_readiness_returns_503_when_database_is_unreachable(): void
    {
        $originalDefault = config('database.default');
        $originalSqlitePath = config('database.connections.sqlite.database');

        try {
            $missingParent = sys_get_temp_dir().DIRECTORY_SEPARATOR.'signrhflow-health-missing-'.uniqid('t', true);
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $missingParent.DIRECTORY_SEPARATOR.'db.sqlite',
            ]);
            DB::purge('sqlite');

            $response = $this->getJson('/api/health');

            $response->assertStatus(503)
                ->assertJsonPath('status', 'unhealthy')
                ->assertJsonPath('checks.database', 'fail');
        } finally {
            config([
                'database.default' => $originalDefault,
                'database.connections.sqlite.database' => $originalSqlitePath,
            ]);
            DB::purge('sqlite');
        }
    }

    public function test_liveness_up_route_returns_success_without_redis(): void
    {
        $this->get('/up')->assertOk();
    }
}
