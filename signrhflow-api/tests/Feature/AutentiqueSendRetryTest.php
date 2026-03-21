<?php

namespace Tests\Feature;

use App\Jobs\SendContractToAutentique;
use App\Models\Contract;
use App\Models\Employee;
use App\Services\AutentiqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AutentiqueSendRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_contract_job_succeeds_after_autentique_transient_failure(): void
    {
        Storage::fake('local');
        RateLimiter::clear('autentique:send-contracts');

        config()->set('services.autentique.token', 'test-token');
        config()->set('services.autentique.graphql_url', 'https://example.test/graphql');

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push(['errors' => [['message' => 'temporary']]], 500)
                ->push([
                    'data' => [
                        'createDocument' => [
                            'id' => 'doc-retry-success',
                            'signatures' => [['link' => ['short_link' => 'https://sign.example/abc']]],
                        ],
                    ],
                ], 200),
        ]);

        $employee = Employee::query()->create([
            'name' => 'Carlos Retry',
            'email' => 'carlos.retry@gmail.com',
            'phone' => '+5511966665555',
            'cpf' => '52998224725',
        ]);

        Storage::disk('local')->put('contracts/retry.pdf', 'fake pdf');

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'status' => Contract::STATUS_DRAFT,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/retry.pdf',
        ]);

        $job = new SendContractToAutentique($contract->id);

        try {
            $job->handle(app(AutentiqueService::class));
            $this->fail('Primeira chamada deveria falhar com HTTP 500 da Autentique.');
        } catch (RuntimeException) {
        }

        $job->handle(app(AutentiqueService::class));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'autentique_document_id' => 'doc-retry-success',
            'status' => Contract::STATUS_PENDING,
        ]);
    }
}
