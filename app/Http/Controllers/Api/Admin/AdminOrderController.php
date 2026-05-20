<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index()
    {
        $orders = Order::with(['buyer', 'seller', 'agent'])
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $orders]);
    }

    public function confirmG4sPickup(Order $order, Request $request)
    {
        $this->authorize('confirm-g4s-pickup');

        $request->validate([
            'tracking_ref' => 'nullable|string|max:255',
        ]);

        $this->orderService->confirmG4sPickup($order, $request->tracking_ref);

        return app(SuccessResponse::class, [
            'data' => $order->fresh(),
            'message' => 'G4S pickup confirmed',
        ]);
    }

    public function setAutoRelease(Order $order, Request $request)
    {
        $this->authorize('auto-release-orders');

        $request->validate([
            'release_hours' => 'required|integer|min:1|max:168',
        ]);

        $this->orderService->setAutoRelease($order, (int) $request->release_hours);

        return app(SuccessResponse::class, [
            'data' => $order->fresh(),
            'message' => 'Auto-release scheduled',
        ]);
    }

    public function g4sPendingRelease(Request $request)
    {
        if (! $request->user()->hasAnyPermission(['confirm-g4s-pickup', 'auto-release-orders'])) {
            abort(403);
        }

        $statuses = $request->status ? [$request->status] : ['verified', 'g4s_pickup_confirmed'];

        $orders = Order::with(['buyer', 'seller', 'agent'])
            ->where('delivery_type', 'g4s')
            ->whereIn('status', $statuses)
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $orders]);
    }

    public function g4sDetails(Order $order)
    {
        if (! request()->user()->hasAnyPermission(['confirm-g4s-pickup', 'auto-release-orders'])) {
            abort(403);
        }

        if ($order->delivery_type !== 'g4s') {
            return app(ErrorResponse::class, ['message' => 'Not a G4S order']);
        }

        return app(SuccessResponse::class, [
            'data' => $order->load(['buyer', 'seller', 'agent']),
        ]);
    }
}
