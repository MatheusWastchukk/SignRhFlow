<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\WebhookLog;
use App\Support\Metrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $webhookLogId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $webhookLog = WebhookLog::query()->findOrFail($this->webhookLogId);

        if ($webhookLog->processed) {
            return;
        }

        try {
            $nextStatus = $this->mapEventToStatus($webhookLog->event_type);

            if ($nextStatus !== null) {
                $contract = Contract::query()
                    ->where('autentique_document_id', $webhookLog->autentique_document_id)
                    ->first();

                if ($contract === null) {
                    $webhookLog->forceFill([
                        'processed' => true,
                        'error_message' => 'Contrato nao encontrado para o document_id informado.',
                    ])->save();
                    Metrics::increment('webhook_processed_total');

                    return;
                }

                if ($this->canTransition($contract->status, $nextStatus)) {
                    $payload = ['status' => $nextStatus];
                    if ($nextStatus === Contract::STATUS_SIGNED && $contract->signed_at === null) {
                        $payload['signed_at'] = now();
                    }

                    $contract->forceFill($payload)->save();
                }
            }

            $webhookLog->forceFill([
                'processed' => true,
                'error_message' => null,
            ])->save();
            Metrics::increment('webhook_processed_total');
        } catch (Throwable $exception) {
            $webhookLog->forceFill([
                'processed' => true,
                'error_message' => $exception->getMessage(),
            ])->save();
            Metrics::increment('webhook_processed_total');

            throw $exception;
        }
    }

    private function mapEventToStatus(?string $eventType): ?string
    {
        $event = strtolower((string) $eventType);

        if (str_contains($event, 'signed') || str_contains($event, 'completed') || str_contains($event, 'assinad')) {
            return Contract::STATUS_SIGNED;
        }

        if (str_contains($event, 'rejected') || str_contains($event, 'refused') || str_contains($event, 'canceled') || str_contains($event, 'rejeit') || str_contains($event, 'recus')) {
            return Contract::STATUS_REJECTED;
        }

        if (str_contains($event, 'created') || str_contains($event, 'sent') || str_contains($event, 'pending') || str_contains($event, 'visualiz') || str_contains($event, 'receb')) {
            return Contract::STATUS_PENDING;
        }

        return null;
    }

    private function canTransition(string $currentStatus, string $nextStatus): bool
    {
        if ($currentStatus === $nextStatus) {
            return false;
        }

        $terminalStates = [
            Contract::STATUS_SIGNED,
            Contract::STATUS_REJECTED,
        ];

        if (in_array($currentStatus, $terminalStates, true)) {
            return false;
        }

        return true;
    }
}
