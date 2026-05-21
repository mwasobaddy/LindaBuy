<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\AuditService;
use App\Services\OrderExpiryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function handle(OrderExpiryService $expiryService, AuditService $auditService): void
    {
        $this->order->refresh();

        $statusBefore = $this->order->status;

        $expiryService->handleOrderExpiry($this->order);

        $this->order->refresh();

        if ($statusBefore !== $this->order->status) {
            $auditService->log(
                action: match ($this->order->status) {
                    'cancelled' => 'order.reversal_initiated',
                    'expired' => 'order.expired',
                    default => 'order.status_changed',
                },
                entity: 'order',
                entityId: $this->order->id,
                details: [
                    'status_before' => $statusBefore,
                    'status_after' => $this->order->status,
                ],
            );
        }
    }
}
