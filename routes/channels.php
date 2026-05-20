<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

if (class_exists(Order::class)) {
    Broadcast::channel('order.{orderId}', function ($user, $orderId) {
        $order = Order::with('seller')->find($orderId);

        return $order && (
            $order->buyer_id === $user->id ||
            ($order->seller && $order->seller->user_id === $user->id)
        );
    });
}

Broadcast::channel('agents.orders', function ($user) {
    return $user->hasPermissionTo('verify-orders') ? $user->toArray() : false;
});
