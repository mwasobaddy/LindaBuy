<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAvailableForVerification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'item_description' => $this->order->item_description,
            'price' => $this->order->price,
            'delivery_type' => $this->order->delivery_type,
            'delivery_location' => $this->order->delivery_location,
            'seller_shop' => $this->order->seller?->shop_name,
            'status' => $this->order->status,
        ];
    }
}
