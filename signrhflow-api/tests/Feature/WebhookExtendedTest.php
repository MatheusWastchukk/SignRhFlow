<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookJob;
use App\Services\AutentiqueWebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_nested_payload_maps_document_and_event_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'documento' => ['uuid' => 'doc-legacy-uuid'],
            'partes' => [
                ['assinado' => ['created' => '2024-01-01T00:00:00Z']],
            ],
        ];

        $response = $this->postJson('/api/webhooks/autentique', $payload);

        $response->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);

        $this->assertDatabaseHas('webhook_logs', [
            'autentique_document_id' => 'doc-legacy-uuid',
            'event_type' => 'signed',
        ]);

        Queue::assertPushed(ProcessWebhookJob::class, 1);
    }

    public function test_webhook_rejects_invalid_hmac_when_secret_is_configured(): void
    {
        Queue::fake();
        config(['services.autentique.webhook_secret' => 'segredo-webhook-teste']);

        $raw = json_encode([
            'event_type' => 'document.signed',
            'data' => ['id' => 'doc-hmac'],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/api/webhooks/autentique', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTENTIQUE_SIGNATURE' => 'assinatura_invalida',
        ], $raw);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_webhook_accepts_valid_hmac_when_secret_is_configured(): void
    {
        Queue::fake();
        $secret = 'segredo-webhook-teste';
        config(['services.autentique.webhook_secret' => $secret]);

        $raw = json_encode([
            'event_type' => 'document.signed',
            'data' => ['id' => 'doc-hmac-ok'],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $raw, $secret);

        $response = $this->call('POST', '/api/webhooks/autentique', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTENTIQUE_SIGNATURE' => $signature,
        ], $raw);

        $response->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);

        Queue::assertPushed(ProcessWebhookJob::class, 1);
    }

    public function test_idempotency_is_by_exact_payload_hash_not_semantic_equality(): void
    {
        Queue::fake();

        $a = ['event_type' => 'document.signed', 'data' => ['id' => 'doc-1']];
        $b = ['data' => ['id' => 'doc-1'], 'event_type' => 'document.signed'];

        $this->postJson('/api/webhooks/autentique', $a)->assertOk()->assertJson(['duplicate' => false]);
        $this->postJson('/api/webhooks/autentique', $b)->assertOk()->assertJson(['duplicate' => false]);

        $this->assertDatabaseCount('webhook_logs', 2);
        Queue::assertPushed(ProcessWebhookJob::class, 2);
    }

    public function test_signature_header_constant_matches_autentique_docs(): void
    {
        $this->assertSame('X-Autentique-Signature', AutentiqueWebhookVerifier::SIGNATURE_HEADER);
    }
}
