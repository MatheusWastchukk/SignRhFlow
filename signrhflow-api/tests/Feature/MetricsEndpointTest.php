<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    public function test_metrics_returns_403_without_bearer_token(): void
    {
        $this->getJson('/api/metrics')->assertForbidden();
    }

    public function test_metrics_returns_403_with_wrong_bearer_token(): void
    {
        $this->withToken('wrong-token')->getJson('/api/metrics')->assertForbidden();
    }

    public function test_metrics_returns_snapshot_with_valid_token(): void
    {
        $response = $this->withToken('test-metrics-token')->getJson('/api/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'counters' => [
                    'webhook_received_total',
                    'webhook_duplicate_total',
                    'webhook_processed_total',
                    'contracts_sent_autentique_total',
                ],
                'app',
            ]);
    }
}
