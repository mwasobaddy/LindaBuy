<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

if (class_exists(Order::class)) {
    Broadcast::channel('order.{orderId}', function ($user, $orderId) {
        $order = Order::find($orderId);

        return $order && (
            $order->buyer_id === $user->id ||
            $order->seller_id === $user->id
        );
    });
}
