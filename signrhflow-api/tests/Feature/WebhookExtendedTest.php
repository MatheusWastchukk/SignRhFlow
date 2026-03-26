<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookJob;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\WebhookLog;
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

    public function test_autentique_v2_signature_accepted_maps_document_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'id' => 'wh_cfg_1',
            'object' => 'webhook',
            'event' => [
                'id' => 'evt_1',
                'type' => 'signature.accepted',
                'data' => [
                    'object' => 'signature',
                    'document' => 'f48a8b465d02dd87559e08f06c41e3b6d548c4d7ad835eb0f',
                    'signed' => '2025-01-31T12:22:30.000000Z',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/autentique', $payload);

        $response->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);

        $this->assertDatabaseHas('webhook_logs', [
            'autentique_document_id' => 'f48a8b465d02dd87559e08f06c41e3b6d548c4d7ad835eb0f',
            'event_type' => 'signature.accepted',
        ]);

        Queue::assertPushed(ProcessWebhookJob::class, 1);
    }

    public function test_autentique_v2_document_object_nested_id_maps_document(): void
    {
        Queue::fake();

        $payload = [
            'event' => [
                'type' => 'document.finished',
                'data' => [
                    'object' => [
                        'id' => '89c7d2ab31f9f5a13b3d20ecf53319af387e54d240ae7be993',
                        'object' => 'document',
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/autentique', $payload)->assertOk();

        $this->assertDatabaseHas('webhook_logs', [
            'autentique_document_id' => '89c7d2ab31f9f5a13b3d20ecf53319af387e54d240ae7be993',
            'event_type' => 'document.finished',
        ]);
    }

    public function test_process_webhook_job_marks_contract_signed_for_signature_accepted(): void
    {
        $employee = Employee::query()->create([
            'name' => 'Webhook Signer',
            'email' => 'webhook.signer@example.com',
            'phone' => '+5511987654321',
            'cpf' => '52998224725',
        ]);

        $documentId = 'f48a8b465d02dd87559e08f06c41e3b6d548c4d7ad835eb0f';

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'autentique_document_id' => $documentId,
            'status' => Contract::STATUS_PENDING,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/webhook-test.pdf',
        ]);

        $log = WebhookLog::query()->create([
            'event_hash' => hash('sha256', uniqid('sig_acc_', true)),
            'autentique_document_id' => $documentId,
            'event_type' => 'signature.accepted',
            'payload' => [],
            'processed' => false,
        ]);

        (new ProcessWebhookJob($log->id))->handle();

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_SIGNED,
        ]);
    }
}
