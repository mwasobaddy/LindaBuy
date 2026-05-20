<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\CreatedResponse;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\ForbiddenResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\Withdrawal;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function request(Request $request)
    {
        $user = $request->user();

        if (! $user->hasRole('seller')) {
            return app(ForbiddenResponse::class, ['message' => 'Only sellers can request withdrawals.']);
        }

        $validated = $request->validate([
            'amount_cents' => 'required|integer|min:1',
        ]);

        $seller = $user->sellers()->first();

        if (! $seller) {
            return app(ErrorResponse::class, ['message' => 'No seller account found.', 'status' => 422]);
        }

        $receivableAccount = $this->ledgerService->getOrCreateSellerReceivableAccount($seller);
        $balance = $receivableAccount->balance;

        if ($balance < $validated['amount_cents']) {
            return app(ErrorResponse::class, ['message' => 'Insufficient receivable balance.', 'status' => 422]);
        }

        $withdrawal = Withdrawal::create([
            'seller_id' => $seller->id,
            'amount' => $validated['amount_cents'],
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return app(CreatedResponse::class, [
            'data' => $withdrawal,
            'message' => 'Withdrawal request submitted.',
        ]);
    }

    public function myWithdrawals(Request $request)
    {
        $user = $request->user();
        $seller = $user->sellers()->first();

        if (! $seller) {
            return app(SuccessResponse::class, ['data' => []]);
        }

        $withdrawals = Withdrawal::where('seller_id', $seller->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $withdrawals]);
    }
}
