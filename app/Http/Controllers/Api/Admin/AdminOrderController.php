<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Jobs\RetryReversal;
use App\Models\Agent;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected LedgerService $ledgerService,
        protected AuditService $auditService,
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

        $this->auditService->log(
            action: 'admin.order.g4s_pickup_confirmed',
            entity: 'order',
            entityId: $order->id,
            details: ['tracking_ref' => $request->tracking_ref],
            request: $request,
        );

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

        $this->auditService->log(
            action: 'admin.order.auto_release_scheduled',
            entity: 'order',
            entityId: $order->id,
            details: ['release_hours' => (int) $request->release_hours],
            request: $request,
        );

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

    public function failedReversals(Request $request)
    {
        if (! $request->user()->hasPermissionTo('handle-failed-reversals')) {
            abort(403);
        }

        $orders = Order::whereNotNull('reversal_failed_at')
            ->whereNull('reversal_resolved_at')
            ->with(['buyer', 'seller'])
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $orders]);
    }

    public function retryReversal(Order $order, Request $request)
    {
        if (! $request->user()->hasPermissionTo('handle-failed-reversals')) {
            abort(403);
        }

        if ($order->reversal_failed_at === null || $order->reversal_resolved_at !== null) {
            return app(ErrorResponse::class, [
                'message' => 'Order does not have a failed reversal or is already resolved.',
                'status' => 422,
            ]);
        }

        if ($request->filled('mpesa_transaction_id')) {
            $order->update(['mpesa_transaction_id' => $request->mpesa_transaction_id]);
        }

        if ($order->mpesa_transaction_id === null) {
            return app(ErrorResponse::class, [
                'message' => 'M-Pesa Transaction ID is required to retry reversal.',
                'status' => 422,
            ]);
        }

        RetryReversal::dispatch($order);

        $this->auditService->log(
            action: 'admin.order.reversal_retried',
            entity: 'order',
            entityId: $order->id,
            details: ['mpesa_transaction_id' => $order->mpesa_transaction_id],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $order->fresh(),
            'message' => 'Reversal retry dispatched.',
        ]);
    }

    public function resolveReversal(Order $order, Request $request)
    {
        if (! $request->user()->hasPermissionTo('handle-failed-reversals')) {
            abort(403);
        }

        $request->validate([
            'resolution_type' => 'required|in:force_release,write_off',
        ]);

        if ($order->reversal_failed_at === null || $order->reversal_resolved_at !== null) {
            return app(ErrorResponse::class, [
                'message' => 'Order does not have a failed reversal or is already resolved.',
                'status' => 422,
            ]);
        }

        if ($order->status !== 'funds_locked') {
            return app(ErrorResponse::class, [
                'message' => 'Order must be in funds_locked status to resolve reversal.',
                'status' => 422,
            ]);
        }

        if ($request->resolution_type === 'force_release') {
            $this->ledgerService->recordEscrowRelease($order);

            $order->update([
                'status' => 'released',
                'reversal_resolved_at' => now(),
                'reversal_resolution_type' => 'force_release',
            ]);

            $this->auditService->log(
                action: 'admin.order.reversal_resolved',
                entity: 'order',
                entityId: $order->id,
                details: ['resolution_type' => 'force_release'],
                request: $request,
            );

            return app(SuccessResponse::class, [
                'data' => $order->fresh(),
                'message' => 'Funds force-released to seller.',
            ]);
        }

        // write_off
        $this->ledgerService->recordWriteOff($order, $order->price);

        $order->update([
            'status' => 'cancelled',
            'reversal_resolved_at' => now(),
            'reversal_resolution_type' => 'write_off',
        ]);

        $this->auditService->log(
            action: 'admin.order.reversal_resolved',
            entity: 'order',
            entityId: $order->id,
            details: ['resolution_type' => 'write_off'],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $order->fresh(),
            'message' => 'Reversal written off as loss.',
        ]);
    }

    public function assignAgent(Request $request, Order $order)
    {
        $this->authorize('manage-orders');

        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        $agent = Agent::findOrFail($validated['agent_id']);

        if ($agent->kyc_status !== 'APPROVED') {
            return app(ErrorResponse::class, [
                'message' => 'Agent KYC is not approved.',
                'status' => 422,
            ]);
        }

        if ($order->agent_id !== null) {
            return app(ErrorResponse::class, [
                'message' => 'Order already has an assigned agent.',
                'status' => 422,
            ]);
        }

        $order->update(['agent_id' => $agent->id]);

        $this->auditService->log(
            action: 'admin.order.agent_assigned',
            entity: 'order',
            entityId: $order->id,
            details: ['agent_id' => $agent->id],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $order->fresh()->load(['buyer', 'seller', 'agent']),
            'message' => 'Agent assigned to order.',
        ]);
    }

    public function removeAgent(Request $request, Order $order)
    {
        $this->authorize('manage-orders');

        if ($order->agent_id === null) {
            return app(ErrorResponse::class, [
                'message' => 'Order does not have an assigned agent.',
                'status' => 422,
            ]);
        }

        if (in_array($order->status, ['in_transit', 'delivered', 'released'])) {
            return app(ErrorResponse::class, [
                'message' => 'Cannot remove agent from order in '.$order->status.' status.',
                'status' => 422,
            ]);
        }

        $order->update(['agent_id' => null]);

        $this->auditService->log(
            action: 'admin.order.agent_removed',
            entity: 'order',
            entityId: $order->id,
            details: ['previous_agent_id' => $order->agent_id],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $order->fresh()->load(['buyer', 'seller', 'agent']),
            'message' => 'Agent removed from order.',
        ]);
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
