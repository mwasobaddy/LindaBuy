<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\CreatedResponse;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\NotFoundResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\CallbackIdempotency;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Services\MpesaService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected WalletService $walletService,
        protected MpesaService $mpesaService,
        protected AuditService $auditService,
    ) {}

    public function balance(Request $request)
    {
        $user = $request->user();
        $balance = $this->ledgerService->getBuyerBalance($user);

        $account = $this->ledgerService->getOrCreateBuyerWalletAccount($user);
        $history = $this->ledgerService->getAccountHistory($account);

        return app(SuccessResponse::class, ['data' => [
            'balance' => [
                'available' => $balance,
                'ledger' => $balance,
            ],
            'recent_transactions' => $history,
        ]]);
    }

    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'amount_cents' => 'required|integer|min:1000',
        ]);

        try {
            $result = $this->walletService->initiateTopUp(
                $request->user(),
                $validated['amount_cents']
            );

            $this->auditService->log(
                action: 'wallet.topup_initiated',
                entity: 'wallet',
                details: ['amount' => $validated['amount_cents']],
                request: $request,
            );
        } catch (\InvalidArgumentException $e) {
            return app(ErrorResponse::class, ['message' => $e->getMessage(), 'status' => 422]);
        }

        return app(CreatedResponse::class, [
            'data' => $result,
            'message' => 'Check your phone to enter M-Pesa PIN',
        ]);
    }

    public function callback(Request $request)
    {
        $payload = $request->all();

        if (! $this->mpesaService->validateCallback($payload, $request)) {
            return app(ErrorResponse::class, ['message' => 'Invalid callback signature.', 'status' => 400]);
        }

        $this->walletService->processCallback($payload);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
        ]);
    }

    public function status(Request $request, string $checkoutRequestId)
    {
        $callbackRecord = CallbackIdempotency::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $callbackRecord) {
            return app(NotFoundResponse::class, ['message' => 'Checkout request not found.']);
        }

        if ($callbackRecord->processed_at !== null) {
            return app(SuccessResponse::class, ['data' => [
                'status' => $callbackRecord->result_code === 0 ? 'completed' : 'failed',
                'result_code' => $callbackRecord->result_code,
                'description' => $callbackRecord->result_code === 0 ? 'Transaction completed' : 'Transaction failed',
            ]]);
        }

        try {
            $status = $this->mpesaService->stkQuery($checkoutRequestId);
        } catch (\Throwable $e) {
            return app(SuccessResponse::class, ['data' => [
                'status' => 'pending',
                'result_code' => null,
                'description' => 'Still processing',
            ]]);
        }

        return app(SuccessResponse::class, ['data' => [
            'status' => 'pending',
            'result_code' => $status['ResultCode'] ?? null,
            'description' => $status['ResultDesc'] ?? 'Still processing',
        ]]);
    }

    public function depositHistory(Request $request)
    {
        $user = $request->user();
        $account = $this->ledgerService->getOrCreateBuyerWalletAccount($user);
        $history = $this->ledgerService->getAccountHistory($account, 100);

        return app(SuccessResponse::class, ['data' => $history]);
    }

    public function reversalResult(Request $request)
    {
        $payload = $request->all();

        if (! $this->mpesaService->validateCallback($payload, $request)) {
            return app(ErrorResponse::class, ['message' => 'Invalid callback signature.', 'status' => 400]);
        }

        $parsed = $this->mpesaService->parseReversalResult($payload);

        if (blank($parsed['transaction_id'])) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        $order = Order::where('mpesa_transaction_id', $parsed['transaction_id'])->first();

        if (! $order) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        if ($order->reversal_resolved_at !== null) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        if ($parsed['amount'] !== null && $order->price !== null && app()->isProduction()) {
            if ((int) $parsed['amount'] !== (int) ($order->price / 100)) {
                Log::critical('M-Pesa reversal callback amount mismatch.', [
                    'order_id' => $order->id,
                    'expected_amount' => $order->price,
                    'callback_amount' => $parsed['amount'],
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            }
        }

        if ((int) $parsed['result_code'] === 0) {
            $this->ledgerService->recordReversal($order, $order->price);

            $order->update([
                'status' => 'cancelled',
                'reversal_failed_at' => null,
                'reversal_failure_reason' => null,
            ]);

            $this->auditService->log(
                action: 'order.reversal_completed_via_callback',
                entity: 'order',
                entityId: $order->id,
                details: [
                    'transaction_id' => $parsed['transaction_id'],
                    'result_desc' => $parsed['result_desc'],
                ],
                request: $request,
            );
        } else {
            $order->update([
                'reversal_failure_reason' => $parsed['result_desc'] ?: 'Reversal failed via callback',
                'reversal_failed_at' => now(),
                'reversal_attempts' => $order->reversal_attempts + 1,
            ]);

            $this->auditService->log(
                action: 'order.reversal_failed_via_callback',
                entity: 'order',
                entityId: $order->id,
                details: [
                    'transaction_id' => $parsed['transaction_id'],
                    'result_code' => $parsed['result_code'],
                    'result_desc' => $parsed['result_desc'],
                ],
                request: $request,
            );
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    public function reversalTimeout(Request $request)
    {
        $payload = $request->all();

        if (! $this->mpesaService->validateCallback($payload, $request)) {
            return app(ErrorResponse::class, ['message' => 'Invalid callback signature.', 'status' => 400]);
        }

        $parsed = $this->mpesaService->parseReversalTimeout($payload);

        if (blank($parsed['transaction_id'])) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        $order = Order::where('mpesa_transaction_id', $parsed['transaction_id'])->first();

        if (! $order) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        if ($order->reversal_resolved_at !== null) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        $order->update([
            'reversal_attempts' => $order->reversal_attempts + 1,
            'reversal_retry_at' => now()->addHours(1),
            'reversal_failure_reason' => $parsed['result_desc'] ?: 'Reversal timed out via callback',
        ]);

        $this->auditService->log(
            action: 'order.reversal_timed_out_via_callback',
            entity: 'order',
            entityId: $order->id,
            details: [
                'transaction_id' => $parsed['transaction_id'],
                'result_code' => $parsed['result_code'],
                'result_desc' => $parsed['result_desc'],
            ],
            request: $request,
        );

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
}
