<?php

namespace Tests\Feature;

use App\Jobs\SendContractToAutentique;
use App\Models\Contract;
use App\Models\Employee;
use App\Services\AutentiqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutentiqueSigningUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_contract_job_uses_create_link_when_signer_has_email_and_no_link_in_response(): void
    {
        Storage::fake('local');
        RateLimiter::clear('autentique:send-contracts');

        config()->set('services.autentique.token', 'test-token');
        config()->set('services.autentique.graphql_url', 'https://example.test/graphql');

        Http::fake(function (Request $request) {
            $body = $request->body();

            if (is_string($body) && str_contains($body, 'createLinkToSignature')) {
                return Http::response([
                    'data' => [
                        'createLinkToSignature' => [
                            'short_link' => 'https://assina.ae/from-create-link',
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'data' => [
                    'createDocument' => [
                        'id' => 'doc-email-signer',
                        'signatures' => [
                            [
                                'public_id' => 'sig-public-xyz',
                                'email' => 'signer@example.com',
                                'link' => null,
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        $employee = Employee::query()->create([
            'name' => 'Signatario Email',
            'email' => 'signer@example.com',
            'phone' => '+5511987654321',
            'cpf' => '39053344705',
        ]);

        Storage::disk('local')->put('contracts/sign-url.pdf', 'fake pdf');

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'status' => Contract::STATUS_DRAFT,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/sign-url.pdf',
        ]);

        (new SendContractToAutentique($contract->id))->handle(app(AutentiqueService::class));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'autentique_document_id' => 'doc-email-signer',
            'autentique_signing_url' => 'https://assina.ae/from-create-link',
        ]);
    }

    public function test_signing_context_resolves_autentique_url_via_document_query_and_create_link(): void
    {
        config()->set('services.autentique.token', 'test-token');
        config()->set('services.autentique.graphql_url', 'https://example.test/graphql');

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push([
                    'data' => [
                        'document' => [
                            'signatures' => [
                                [
                                    'public_id' => 'sig-ctx-1',
                                    'email' => 'ctx@example.com',
                                    'link' => null,
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'createLinkToSignature' => [
                            'short_link' => 'https://assina.ae/from-signing-context',
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'document' => [
                            'signatures_count' => 1,
                            'signed_count' => 0,
                            'rejected_count' => 0,
                        ],
                    ],
                ], 200),
        ]);

        $employee = Employee::query()->create([
            'name' => 'Ctx User',
            'email' => 'ctx@example.com',
            'phone' => '+5511912345678',
            'cpf' => '52998224725',
        ]);

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'autentique_document_id' => 'doc-ctx-1',
            'autentique_signing_url' => null,
            'status' => Contract::STATUS_PENDING,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/ctx.pdf',
        ]);

        $token = (string) $contract->signing_token;

        $response = $this->getJson("/api/signing/{$token}/context");

        $response->assertOk()
            ->assertJsonPath('contract.autentique_signing_url', 'https://assina.ae/from-signing-context');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'autentique_signing_url' => 'https://assina.ae/from-signing-context',
        ]);
    }

    public function test_signing_context_uses_panel_assinar_url_when_graphql_unreachable(): void
    {
        config()->set('services.autentique.token', 'test-token');
        config()->set('services.autentique.graphql_url', 'https://example.test/graphql');

        Http::fake([
            'example.test/*' => Http::response('service unavailable', 503),
        ]);

        $employee = Employee::query()->create([
            'name' => 'Panel Fallback',
            'email' => 'panel@example.com',
            'phone' => '+5511911122233',
            'cpf' => '39053344705',
        ]);

        $hexDocumentId = '9a0c83eaa9317aece45bb0cae9982e5bb7718ce9';

        $contract = Contract::query()->create([
            'employee_id' => $employee->id,
            'autentique_document_id' => $hexDocumentId,
            'autentique_signing_url' => null,
            'status' => Contract::STATUS_PENDING,
            'delivery_method' => Contract::DELIVERY_EMAIL,
            'file_path' => 'contracts/panel.pdf',
        ]);

        $token = (string) $contract->signing_token;

        $expectedUrl = 'https://painel.autentique.com.br/assinar/'.$hexDocumentId;

        $response = $this->getJson("/api/signing/{$token}/context");

        $response->assertOk()
            ->assertJsonPath('contract.autentique_signing_url', $expectedUrl);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'autentique_signing_url' => $expectedUrl,
        ]);
    }
}
