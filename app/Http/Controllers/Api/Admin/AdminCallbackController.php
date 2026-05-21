<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\CallbackIdempotency;
use App\Services\AuditService;
use App\Services\MpesaService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class AdminCallbackController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected MpesaService $mpesaService,
        protected AuditService $auditService,
    ) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPermissionTo('view-callbacks')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,success,failed,timed_out,retried',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'phone' => 'nullable|string|max:20',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CallbackIdempotency::with('user');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (! empty($validated['phone'])) {
            $query->where('phone', 'like', '%'.$validated['phone'].'%');
        }

        $perPage = $validated['per_page'] ?? 50;

        $callbacks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return app(SuccessResponse::class, ['data' => $callbacks]);
    }

    public function show(Request $request, CallbackIdempotency $callback)
    {
        if (! $request->user()->hasPermissionTo('view-callbacks')) {
            abort(403);
        }

        $callback->load('user');

        return app(SuccessResponse::class, ['data' => $callback]);
    }

    public function retry(Request $request, CallbackIdempotency $callback)
    {
        if (! $request->user()->hasPermissionTo('handle-callbacks')) {
            abort(403);
        }

        if ($callback->isSuccessful()) {
            abort(422, 'Cannot retry a successful callback.');
        }

        if (! $callback->isFailed() && ! $callback->isTimedOut()) {
            abort(422, 'Callback must be in failed or timed_out status to retry.');
        }

        $checkoutRequestId = $callback->checkout_request_id;

        $callback->processed_at = null;
        $callback->result_code = null;
        $callback->status = 'pending';
        $callback->increment('retry_count');
        $callback->last_retried_at = now();
        $callback->save();

        try {
            if ($callback->callback_payload) {
                $this->walletService->processCallback($callback->callback_payload);
            } else {
                $result = $this->mpesaService->stkQuery($checkoutRequestId);

                $resultCode = $result['ResultCode'] ?? null;

                if ($resultCode !== null) {
                    $fakePayload = [
                        'Body' => [
                            'stkCallback' => [
                                'MerchantRequestID' => $result['MerchantRequestID'] ?? '',
                                'CheckoutRequestID' => $checkoutRequestId,
                                'ResultCode' => (int) $resultCode,
                                'ResultDesc' => $result['ResultDesc'] ?? '',
                                'CallbackMetadata' => $result['CallbackMetadata'] ?? [],
                            ],
                        ],
                    ];

                    $this->walletService->processCallback($fakePayload);
                }
            }

            $callback->refresh();

            $this->auditService->log(
                action: 'admin.callback.retried',
                entity: 'callback',
                entityId: $callback->id,
                details: [
                    'user_id' => $callback->user_id,
                    'amount' => $callback->amount,
                    'correlation_id' => $callback->correlation_id,
                    'new_status' => $callback->status,
                ],
            );

            return app(SuccessResponse::class, [
                'data' => $callback,
                'message' => 'Callback retry completed.',
            ]);
        } catch (\Throwable $e) {
            $this->auditService->log(
                action: 'admin.callback.retry_failed',
                entity: 'callback',
                entityId: $callback->id,
                details: [
                    'error' => $e->getMessage(),
                    'correlation_id' => $callback->correlation_id,
                ],
            );

            throw $e;
        }
    }
}
