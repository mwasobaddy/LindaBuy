<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chat\StoreMessageRequest;
use App\Http\Responses\Api\CreatedResponse;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\ForbiddenResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\ChatMessage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private function isParticipant(Order $order, Request $request): bool
    {
        $user = $request->user();

        return $order->buyer_id === $user->id
            || ($order->seller && $order->seller->user_id === $user->id);
    }

    private function canSendMessage(Order $order): bool
    {
        return ! in_array($order->status, [
            'cancelled',
            'expired',
            'released',
            'payment_failed',
        ]);
    }

    public function index(Order $order, Request $request)
    {
        if (! $this->isParticipant($order, $request)) {
            return app(ForbiddenResponse::class, [
                'message' => 'You are not a participant in this order.',
            ]);
        }

        $messages = ChatMessage::with('sender:id,name')
            ->where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $messages->getCollection()->transform(function ($message) {
            return [
                'id' => $message->id,
                'order_id' => $message->order_id,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'message' => $message->message,
                'created_at' => $message->created_at,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                ] : null,
            ];
        });

        return app(SuccessResponse::class, ['data' => $messages]);
    }

    public function store(StoreMessageRequest $request, Order $order)
    {
        $user = $request->user();

        if (! $this->isParticipant($order, $request)) {
            return app(ForbiddenResponse::class, [
                'message' => 'You are not a participant in this order.',
            ]);
        }

        if (! $this->canSendMessage($order)) {
            return app(ErrorResponse::class, [
                'message' => 'Cannot send messages on a completed or cancelled order.',
                'status' => 422,
            ]);
        }

        $senderType = $order->buyer_id === $user->id ? 'buyer' : 'seller';

        $message = ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $user->id,
            'sender_type' => $senderType,
            'message' => $request->input('message'),
        ]);

        $message->load('sender:id,name');

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast chat message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return app(CreatedResponse::class, [
            'data' => [
                'id' => $message->id,
                'order_id' => $message->order_id,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'message' => $message->message,
                'created_at' => $message->created_at,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                ] : null,
            ],
            'message' => 'Message sent.',
        ]);
    }
}
