<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderExpiryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePayment implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function handle(OrderExpiryService $expiryService): void
    {
        $this->order->refresh();

        $expiryService->handlePaymentExpiry($this->order);
    }
}
