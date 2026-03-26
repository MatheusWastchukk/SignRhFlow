<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\AutentiqueService;
use App\Support\Metrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendContractToAutentique implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public string $contractId)
    {
        $this->onQueue('contracts');
    }

    public function handle(AutentiqueService $autentiqueService): void
    {
        $rateLimitKey = 'autentique:send-contracts';

        if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            $this->release(max(1, RateLimiter::availableIn($rateLimitKey)));

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $contract = Contract::query()
            ->with('employee')
            ->find($this->contractId);

        if ($contract === null) {
            Log::info('SendContractToAutentique ignorado: contrato nao existe mais.', [
                'contract_id' => $this->contractId,
            ]);

            return;
        }

        $result = $autentiqueService->createDocument($contract);

        $payload = [
            'autentique_document_id' => $result['document_id'],
            'autentique_signing_url' => $result['signing_url'] ?? null,
        ];

        if ($contract->status === Contract::STATUS_DRAFT) {
            $payload['status'] = Contract::STATUS_PENDING;
        }

        $contract->forceFill($payload)->save();

        Log::info('Contrato enviado para Autentique.', [
            'contract_id' => $contract->id,
            'autentique_document_id' => $payload['autentique_document_id'],
            'autentique_signing_url' => $payload['autentique_signing_url'],
        ]);

        Metrics::increment('contracts_sent_autentique_total');
    }

    public function failed(?Throwable $exception): void
    {
        $contract = Contract::query()->find($this->contractId);

        if ($contract === null) {
            return;
        }

        if (in_array($contract->status, [Contract::STATUS_SIGNED, Contract::STATUS_REJECTED], true)) {
            return;
        }

        if ($contract->status !== Contract::STATUS_PENDING) {
            $contract->forceFill([
                'status' => Contract::STATUS_PENDING,
            ])->save();
        }
    }
}
