<?php

namespace App\Http\Controllers\Api;

use App\ApiResponse;
use App\Http\Controllers\Controller;
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
            return ApiResponse::forbidden('Only sellers can request withdrawals.');
        }

        $validated = $request->validate([
            'amount_cents' => 'required|integer|min:1',
        ]);

        $seller = $user->sellers()->first();

        if (! $seller) {
            return ApiResponse::error('No seller account found.', 422);
        }

        $receivableAccount = $this->ledgerService->getOrCreateSellerReceivableAccount($seller);
        $balance = $receivableAccount->balance;

        if ($balance < $validated['amount_cents']) {
            return ApiResponse::error('Insufficient receivable balance.', 422);
        }

        $withdrawal = Withdrawal::create([
            'seller_id' => $seller->id,
            'amount' => $validated['amount_cents'],
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return ApiResponse::success($withdrawal, 'Withdrawal request submitted.', 201);
    }

    public function myWithdrawals(Request $request)
    {
        $user = $request->user();
        $seller = $user->sellers()->first();

        if (! $seller) {
            return ApiResponse::success([]);
        }

        $withdrawals = Withdrawal::where('seller_id', $seller->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($withdrawals);
    }
}
