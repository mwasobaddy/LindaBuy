<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReleaseG4sOrder implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function uniqueId(): string
    {
        return 'order_'.$this->order->id;
    }

    public function handle(OrderService $orderService): void
    {
        $this->order->refresh();

        if (! $this->order->auto_release_enabled) {
            Log::info('G4S auto-release skipped: auto_release_enabled is false', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        if (! in_array($this->order->status, ['g4s_pickup_confirmed', 'verified'])) {
            Log::info('G4S auto-release skipped: invalid status', [
                'order_id' => $this->order->id,
                'status' => $this->order->status,
            ]);

            return;
        }

        if ($this->order->auto_release_at && $this->order->auto_release_at->isFuture()) {
            Log::info('G4S auto-release skipped: release time not yet reached', [
                'order_id' => $this->order->id,
                'auto_release_at' => $this->order->auto_release_at,
            ]);

            return;
        }

        $orderService->adminReleasePayment($this->order);

        Log::info('G4S auto-release executed successfully', [
            'order_id' => $this->order->id,
        ]);
    }
}
