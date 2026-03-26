<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SigningFinalizeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalize_queries_autentique_and_sets_signed_when_complete(): void
    {
        config()->set('services.autentique.token', 'test-token');
        config()->set('services.autentique.graphql_url', 'https://example.test/graphql');

        Http::fake([
            'example.test/*' => Http::response([
                'data' => [
                    'document' => [
                        'signatures_count' => 1,
                        'signed_count' => 1,
                        'rejected_count' => 0,
                    ],
                ],
            ], 200),
        ]);

        $employee = Employee::query()->create([
            'name' => 'Sync Signer',
            'email' => 'sync.signer@example.com',
            'phone' => '+5511988877766',
            'cpf' => '52998224725',
        ]);

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'autentique_document_id' => '9a0c83eaa9317aece45bb0cae9982e5bb7718ce9',
            'autentique_signing_url' => 'https://painel.autentique.com.br/assinar/9a0c83eaa9317aece45bb0cae9982e5bb7718ce9',
            'status' => Contract::STATUS_PENDING,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/sync.pdf',
        ]);

        $token = (string) $contract->signing_token;

        $response = $this->postJson("/api/signing/{$token}/finalize", [
            'delivery_method' => 'EMAIL',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', Contract::STATUS_SIGNED)
            ->assertJsonPath('message', 'Assinatura confirmada na Autentique. Contrato atualizado.');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_SIGNED,
        ]);

        $this->assertNotNull($contract->fresh()->signed_at);
    }
}
