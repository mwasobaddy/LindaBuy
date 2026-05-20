<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatPageController extends Controller
{
    public function show(Order $order, Request $request)
    {
        $user = $request->user();

        $isParticipant = $order->buyer_id === $user->id
            || ($order->seller && $order->seller->user_id === $user->id);

        if (! $isParticipant) {
            abort(403, 'You are not a participant in this order.');
        }

        $order->load(['buyer:id,name', 'seller:id,shop_name,user_id']);

        return Inertia::render('orders/chat', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'price' => $order->price,
                'item_description' => $order->item_description,
                'delivery_type' => $order->delivery_type,
                'created_at' => $order->created_at,
                'buyer' => $order->buyer,
                'seller' => $order->seller,
                'initiator_type' => $order->initiator_type,
            ],
        ]);
    }
}
