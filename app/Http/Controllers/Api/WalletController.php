<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\CreatedResponse;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\NotFoundResponse;
use App\Http\Responses\Api\SuccessResponse;
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

        if (! $this->mpesaService->validateCallback($payload)) {
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
}
