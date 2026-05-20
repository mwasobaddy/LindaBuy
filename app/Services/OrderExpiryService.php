<?php

namespace App\Services;

use App\Models\Order;

class OrderExpiryService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function handleOrderExpiry(Order $order): void
    {
        $currentStatus = $order->status;

        if (in_array($currentStatus, ['pending_accept', 'accepted'])) {
            $order->update(['status' => 'expired']);
        } elseif ($currentStatus === 'funds_locked') {
            try {
                $this->ledgerService->recordReversal($order, $order->price);

                $order->update([
                    'status' => 'cancelled',
                ]);
            } catch (\Throwable $e) {
                $order->update([
                    'reversal_failed_at' => now(),
                    'reversal_failure_reason' => $e->getMessage(),
                ]);
            }
        }
    }

    public function handlePaymentExpiry(Order $order): void
    {
        if ($order->status === 'pending_accept' && $order->initiator_type === 'buyer') {
            $order->update(['status' => 'payment_failed']);
        }
    }
}
