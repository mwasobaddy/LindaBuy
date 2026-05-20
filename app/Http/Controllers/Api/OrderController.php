<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\CreatedResponse;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\ForbiddenResponse;
use App\Http\Responses\Api\OrderCreatedResponse;
use App\Http\Responses\Api\OrderReleaseTokenResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\Order;
use App\Models\OrderIssueReport;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function myOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with(['seller', 'buyer'])
            ->where(function ($query) use ($user) {
                $query->where('buyer_id', $user->id)
                    ->orWhereHas('seller', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $orders]);
    }

    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        $isParticipant = $order->buyer_id === $user->id ||
            $order->seller->user_id === $user->id ||
            $order->agent_id === optional($user->agent)->id;

        if (! $isParticipant && ! $user->hasPermissionTo('view-orders')) {
            return app(ForbiddenResponse::class, ['message' => 'You are not a participant in this order.']);
        }

        $order->load(['seller', 'buyer', 'agent', 'issueReports', 'chatMessages']);

        return app(SuccessResponse::class, ['data' => $order]);
    }

    public function createSellerOrder(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => 'required|integer|exists:sellers,id',
            'buyer_phone' => 'required|string|exists:users,phone',
            'item_description' => 'required|string|max:5000',
            'price' => 'required|integer|min:1000',
            'delivery_type' => 'required|in:shop_delivery,g4s',
            'delivery_location' => 'nullable|string|max:1000',
        ]);

        try {
            $order = $this->orderService->createSellerInitiatedOrder(
                $request->user(),
                $validated
            );

            return app(OrderCreatedResponse::class, [
                'order' => $order->load(['seller', 'buyer']),
                'message' => 'Order created successfully',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function createBuyerOrder(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => 'required|integer|exists:sellers,id',
            'item_description' => 'required|string|max:5000',
            'price' => 'required|integer|min:1000',
            'delivery_type' => 'required|in:shop_delivery,g4s',
            'delivery_location' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->orderService->createBuyerInitiatedOrder(
                $request->user(),
                $validated
            );

            return app(OrderCreatedResponse::class, [
                'order' => $result['order']->load(['seller', 'buyer']),
                'message' => 'Order created. Processing payment...',
                'checkoutRequestId' => $result['checkout_request_id'],
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function accept(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $updatedOrder = $this->orderService->acceptOrder($order, $request->user());

            return app(SuccessResponse::class, [
                'data' => $updatedOrder->load(['seller', 'buyer']),
                'message' => 'Order accepted successfully',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function decline(Request $request, Order $order)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->orderService->declineOrder($order, $request->user(), $validated['reason']);

            return app(SuccessResponse::class, [
                'data' => $order->fresh()->load(['seller', 'buyer']),
                'message' => 'Order declined',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function confirmDelivery(Request $request, Order $order)
    {
        try {
            $this->orderService->confirmDelivery($order, $request->user());

            return app(SuccessResponse::class, [
                'data' => $order->fresh()->load(['seller', 'buyer']),
                'message' => 'Delivery confirmed',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function requestReleaseConfirmation(Request $request, Order $order)
    {
        try {
            $this->orderService->generateReleaseToken($order);

            return app(OrderReleaseTokenResponse::class, ['message' => 'Release confirmation requested']);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function resendReleaseToken(Request $request, Order $order)
    {
        try {
            $this->orderService->resendReleaseToken($order);

            return app(OrderReleaseTokenResponse::class, ['message' => 'New release token sent']);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function release(Request $request, Order $order)
    {
        $validated = $request->validate([
            'confirmation_token' => 'required|string|size:6',
        ]);

        try {
            $this->orderService->releasePayment(
                $order,
                $request->user(),
                $validated['confirmation_token']
            );

            return app(SuccessResponse::class, [
                'data' => $order->fresh()->load(['seller', 'buyer']),
                'message' => 'Payment released successfully',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function agentAvailableJobs(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (! $agent || $agent->kyc_status !== 'APPROVED') {
            return app(ForbiddenResponse::class, ['message' => 'Only approved agents can view jobs.']);
        }

        $orders = Order::with(['seller', 'buyer'])
            ->where('status', 'funds_locked')
            ->whereNull('agent_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $orders]);
    }

    public function agentMyJobs(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (! $agent) {
            return app(ForbiddenResponse::class, ['message' => 'Only agents can view assigned jobs.']);
        }

        $orders = Order::with(['seller', 'buyer'])
            ->where('agent_id', $agent->id)
            ->whereIn('status', ['funds_locked', 'verified', 'in_transit'])
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $orders]);
    }

    public function acceptJob(Request $request, Order $order)
    {
        $agent = $request->user()->agent;

        if (! $agent || $agent->kyc_status !== 'APPROVED') {
            return app(ForbiddenResponse::class, ['message' => 'Only approved agents can accept jobs.']);
        }

        try {
            $this->orderService->acceptJob($order, $agent);

            return app(SuccessResponse::class, [
                'data' => $order->fresh()->load(['seller']),
                'message' => 'Job accepted successfully',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function verify(Request $request, Order $order)
    {
        $agent = $request->user()->agent;

        if (! $agent || $agent->kyc_status !== 'APPROVED') {
            return app(ForbiddenResponse::class, ['message' => 'Only approved agents can verify orders.']);
        }

        $validated = $request->validate([
            'carrier_name' => 'nullable|required_if:delivery_type,shop_delivery|string|max:255',
            'carrier_phone' => 'nullable|required_if:delivery_type,shop_delivery|string|max:20',
            'g4s_branch' => 'nullable|required_if:delivery_type,g4s|string|max:255',
            'g4s_tracking_ref' => 'nullable|string|max:255',
        ]);

        try {
            $this->orderService->verifyAndHandover($order, $agent, $validated);

            return app(SuccessResponse::class, [
                'data' => $order->fresh()->load(['seller', 'buyer', 'agent']),
                'message' => 'Order verified and handed over successfully',
            ]);
        } catch (ValidationException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }
    }

    public function reportIssue(Request $request, Order $order)
    {
        $agent = $request->user()->agent;

        if (! $agent || $agent->kyc_status !== 'APPROVED') {
            return app(ForbiddenResponse::class, ['message' => 'Only approved agents can report issues.']);
        }

        $validated = $request->validate([
            'issue_type' => 'required|in:QUANTITY_MISMATCH,QUALITY_ISSUE,NOT_AS_DESCRIBED,OTHER',
            'description' => 'required|string|max:5000',
        ]);

        $issueReport = OrderIssueReport::create([
            'order_id' => $order->id,
            'agent_id' => $agent->id,
            'issue_type' => $validated['issue_type'],
            'description' => $validated['description'],
            'status' => 'REPORTED',
            'reported_at' => now(),
        ]);

        return app(CreatedResponse::class, [
            'data' => $issueReport,
            'message' => 'Issue reported successfully',
        ]);
    }
}
