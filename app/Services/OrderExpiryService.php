<?php

namespace App\Services;

use App\Models\Order;

class OrderExpiryService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected AuditService $auditService,
    ) {}

    public function handleOrderExpiry(Order $order): void
    {
        $currentStatus = $order->status;

        if (in_array($currentStatus, ['pending_accept', 'accepted'])) {
            $order->update(['status' => 'expired']);

            $this->auditService->log(
                action: 'order.expired',
                entity: 'order',
                entityId: $order->id,
                details: ['status_before' => $currentStatus],
            );
        } elseif ($currentStatus === 'funds_locked') {
            try {
                $this->ledgerService->recordReversal($order, $order->price);

                $order->update([
                    'status' => 'cancelled',
                ]);

                $this->auditService->log(
                    action: 'order.expired_with_reversal',
                    entity: 'order',
                    entityId: $order->id,
                    details: ['status_before' => $currentStatus],
                );
            } catch (\Throwable $e) {
                $order->update([
                    'reversal_failed_at' => now(),
                    'reversal_failure_reason' => $e->getMessage(),
                ]);

                $this->auditService->log(
                    action: 'order.reversal_failed',
                    entity: 'order',
                    entityId: $order->id,
                    details: ['error' => $e->getMessage()],
                );
            }
        }
    }

    public function handlePaymentExpiry(Order $order): void
    {
        if ($order->status === 'pending_accept' && $order->initiator_type === 'buyer') {
            $order->update(['status' => 'payment_failed']);

            $this->auditService->log(
                action: 'order.payment_expired',
                entity: 'order',
                entityId: $order->id,
            );
        }
    }
}
