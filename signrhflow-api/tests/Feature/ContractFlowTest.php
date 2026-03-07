<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookJob;
use App\Jobs\SendContractToAutentique;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\WebhookLog;
use App\Services\AutentiqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_employee_contract_generates_pdf_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $employeeResponse = $this->postJson('/api/employees', [
            'name' => 'Joao Silva',
            'email' => 'joao.silva@gmail.com',
            'phone' => '+5511999999999',
            'cpf' => '52998224725',
        ]);

        $employeeResponse->assertCreated();

        $contractResponse = $this->postJson('/api/contracts', [
            'employee_id' => $employeeResponse->json('id'),
            'delivery_method' => 'EMAIL',
        ]);

        $contractResponse->assertCreated();
        $filePath = (string) $contractResponse->json('file_path');

        $this->assertNotSame('contracts/pending.pdf', $filePath);
        Storage::disk('local')->assertExists($filePath);
        Queue::assertPushed(SendContractToAutentique::class);
    }

    public function test_it_dispatches_webhook_job_once_for_duplicate_payloads(): void
    {
        Queue::fake();

        $payload = [
            'event_type' => 'document.signed',
            'data' => ['id' => 'doc-123'],
        ];

        $firstResponse = $this->postJson('/api/webhooks/autentique', $payload);
        $secondResponse = $this->postJson('/api/webhooks/autentique', $payload);

        $firstResponse->assertOk()->assertJson(['duplicate' => false]);
        $secondResponse->assertOk()->assertJson(['duplicate' => true]);
        $this->assertDatabaseCount('webhook_logs', 1);
        Queue::assertPushed(ProcessWebhookJob::class, 1);
    }

    public function test_it_processes_webhook_and_updates_contract_status(): void
    {
        $employee = Employee::query()->create([
            'name' => 'Maria Souza',
            'email' => 'maria.souza@gmail.com',
            'phone' => '+5511988887777',
            'cpf' => '39053344705',
        ]);

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'autentique_document_id' => 'doc-999',
            'status' => Contract::STATUS_PENDING,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/test.pdf',
        ]);

        $log = WebhookLog::query()->create([
            'event_hash' => hash('sha256', 'doc-999-signed'),
            'autentique_document_id' => 'doc-999',
            'event_type' => 'document.signed',
            'payload' => ['event_type' => 'document.signed'],
            'processed' => false,
        ]);

        (new ProcessWebhookJob($log->id))->handle();

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_SIGNED,
        ]);
        $this->assertDatabaseHas('webhook_logs', [
            'id' => $log->id,
            'processed' => true,
        ]);
    }

    public function test_send_contract_job_updates_document_id_from_autentique_response(): void
    {
        Storage::fake('local');
        RateLimiter::clear('autentique:send-contracts');

        config()->set('services.autentique.token', 'test-token');
        config()->set('services.autentique.graphql_url', 'https://example.test/graphql');

        Http::fake([
            'example.test/*' => Http::response([
                'data' => ['createDocument' => ['id' => 'doc-abc-123']],
            ], 200),
        ]);

        $employee = Employee::query()->create([
            'name' => 'Ana Lima',
            'email' => 'ana.lima@gmail.com',
            'phone' => '+5511977776666',
            'cpf' => '11144477735',
        ]);

        Storage::disk('local')->put('contracts/test-send.pdf', 'fake pdf content');

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'status' => Contract::STATUS_DRAFT,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/test-send.pdf',
        ]);

        $job = new SendContractToAutentique($contract->id);
        $job->handle(app(AutentiqueService::class));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_PENDING,
            'autentique_document_id' => 'doc-abc-123',
        ]);
    }
}
