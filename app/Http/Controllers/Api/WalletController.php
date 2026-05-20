<?php

namespace App\Http\Controllers\Api;

use App\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CallbackIdempotency;
use App\Services\LedgerService;
use App\Services\MpesaService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected WalletService $walletService,
        protected MpesaService $mpesaService
    ) {}

    public function balance(Request $request)
    {
        $user = $request->user();
        $balance = $this->ledgerService->getBuyerBalance($user);

        $account = $this->ledgerService->getOrCreateBuyerWalletAccount($user);
        $history = $this->ledgerService->getAccountHistory($account);

        return ApiResponse::success([
            'balance' => [
                'available' => $balance,
                'ledger' => $balance,
            ],
            'recent_transactions' => $history,
        ]);
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
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'Check your phone to enter M-Pesa PIN', 201);
    }

    public function callback(Request $request)
    {
        $payload = $request->all();

        if (! $this->mpesaService->validateCallback($payload)) {
            return ApiResponse::error('Invalid callback signature.', 400);
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
            return ApiResponse::notFound('Checkout request not found.');
        }

        if ($callbackRecord->processed_at !== null) {
            return ApiResponse::success([
                'status' => $callbackRecord->result_code === 0 ? 'completed' : 'failed',
                'result_code' => $callbackRecord->result_code,
                'description' => $callbackRecord->result_code === 0 ? 'Transaction completed' : 'Transaction failed',
            ]);
        }

        try {
            $status = $this->mpesaService->stkQuery($checkoutRequestId);
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'status' => 'pending',
                'result_code' => null,
                'description' => 'Still processing',
            ]);
        }

        return ApiResponse::success([
            'status' => 'pending',
            'result_code' => $status['ResultCode'] ?? null,
            'description' => $status['ResultDesc'] ?? 'Still processing',
        ]);
    }

    public function depositHistory(Request $request)
    {
        $user = $request->user();
        $account = $this->ledgerService->getOrCreateBuyerWalletAccount($user);
        $history = $this->ledgerService->getAccountHistory($account, 100);

        return ApiResponse::success($history);
    }
}
