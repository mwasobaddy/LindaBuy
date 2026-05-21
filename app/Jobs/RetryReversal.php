<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Services\MpesaService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryReversal implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function uniqueId(): string
    {
        return 'reversal_order_'.$this->order->id;
    }

    public function handle(MpesaService $mpesaService, LedgerService $ledgerService, AuditService $auditService): void
    {
        $this->order->refresh();

        if ($this->order->reversal_failed_at === null || $this->order->reversal_resolved_at !== null) {
            return;
        }

        if ($this->order->mpesa_transaction_id === null) {
            return;
        }

        try {
            $response = $mpesaService->reversal(
                $this->order->mpesa_transaction_id,
                $this->order->price,
                'Reversal for order #'.$this->order->id
            );

            $resultCode = $response['ResponseCode'] ?? null;

            if ($resultCode === '0') {
                $ledgerService->recordReversal($this->order, $this->order->price);

                $this->order->update([
                    'status' => 'cancelled',
                    'reversal_failed_at' => null,
                    'reversal_failure_reason' => null,
                    'reversal_attempts' => $this->order->reversal_attempts + 1,
                ]);

                $auditService->log(
                    action: 'order.reversal_retry_completed',
                    entity: 'order',
                    entityId: $this->order->id,
                    details: ['mpesa_transaction_id' => $this->order->mpesa_transaction_id],
                );
            } else {
                $this->handleFailure($response['ResponseDescription'] ?? 'Unknown M-Pesa error');
                $auditService->log(
                    action: 'order.reversal_retry_failed',
                    entity: 'order',
                    entityId: $this->order->id,
                    details: ['error' => $response['ResponseDescription'] ?? 'Unknown M-Pesa error'],
                );
            }
        } catch (\Throwable $e) {
            $this->handleFailure($e->getMessage());
            $auditService->log(
                action: 'order.reversal_retry_failed',
                entity: 'order',
                entityId: $this->order->id,
                details: ['error' => $e->getMessage()],
            );
        }
    }

    protected function handleFailure(string $errorMessage): void
    {
        $backoffHours = match (true) {
            $this->order->reversal_attempts < 1 => 1,
            $this->order->reversal_attempts < 3 => 6,
            default => 24,
        };

        $this->order->update([
            'reversal_attempts' => $this->order->reversal_attempts + 1,
            'reversal_retry_at' => now()->addHours($backoffHours),
            'reversal_failure_reason' => $errorMessage,
        ]);
    }
}
