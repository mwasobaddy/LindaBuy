<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Services\AuditService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWithdrawalController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected AuditService $auditService,
    ) {}

    public function index(Request $request)
    {
        $query = Withdrawal::with('seller.user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')->get();

        return app(SuccessResponse::class, ['data' => $withdrawals]);
    }

    public function process(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return app(ErrorResponse::class, ['message' => 'Only pending withdrawals can be processed.', 'status' => 422]);
        }

        $withdrawal->update([
            'status' => 'processing',
        ]);

        $this->auditService->log(
            action: 'admin.withdrawal.processed',
            entity: 'withdrawal',
            entityId: $withdrawal->id,
            details: ['amount' => $withdrawal->amount, 'seller_id' => $withdrawal->seller_id],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $withdrawal,
            'message' => 'Withdrawal marked as processing.',
        ]);
    }

    public function complete(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== 'processing' && $withdrawal->status !== 'pending') {
            return app(ErrorResponse::class, ['message' => 'Withdrawal cannot be completed from current status.', 'status' => 422]);
        }

        return DB::transaction(function () use ($withdrawal) {
            $seller = $withdrawal->seller;
            $receivableAccount = $this->ledgerService->getOrCreateSellerReceivableAccount($seller);
            $mpesaFloat = Account::where('account_code', 'MPESA_FLOAT')->firstOrFail();

            $transaction = Transaction::create([
                'transaction_type' => 'withdrawal',
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id,
                'description' => 'Withdrawal completion for seller #'.$seller->id,
                'status' => 'completed',
            ]);

            $withdrawal->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            $lastReceivableEntry = Entry::where('account_id', $receivableAccount->id)
                ->orderBy('id', 'desc')
                ->first();
            $receivablePreviousBalance = $lastReceivableEntry ? $lastReceivableEntry->balance_after : 0;

            $lastMpesaEntry = Entry::where('account_id', $mpesaFloat->id)
                ->orderBy('id', 'desc')
                ->first();
            $mpesaPreviousBalance = $lastMpesaEntry ? $lastMpesaEntry->balance_after : 0;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $receivableAccount->id,
                'debit_amount' => $withdrawal->amount,
                'credit_amount' => 0,
                'balance_after' => $receivablePreviousBalance - $withdrawal->amount,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $mpesaFloat->id,
                'debit_amount' => 0,
                'credit_amount' => $withdrawal->amount,
                'balance_after' => $mpesaPreviousBalance - $withdrawal->amount,
            ]);

            $this->auditService->log(
                action: 'admin.withdrawal.completed',
                entity: 'withdrawal',
                entityId: $withdrawal->id,
                details: ['amount' => $withdrawal->amount, 'seller_id' => $withdrawal->seller_id],
                request: request(),
            );

            return app(SuccessResponse::class, [
                'data' => $withdrawal,
                'message' => 'Withdrawal completed.',
            ]);
        });
    }

    public function fail(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== 'processing' && $withdrawal->status !== 'pending') {
            return app(ErrorResponse::class, ['message' => 'Withdrawal cannot be failed from current status.', 'status' => 422]);
        }

        $validated = $request->validate([
            'failure_reason' => 'required|string|max:1000',
        ]);

        $withdrawal->update([
            'status' => 'failed',
            'failure_reason' => $validated['failure_reason'],
            'processed_at' => now(),
        ]);

        $this->auditService->log(
            action: 'admin.withdrawal.failed',
            entity: 'withdrawal',
            entityId: $withdrawal->id,
            details: [
                'amount' => $withdrawal->amount,
                'seller_id' => $withdrawal->seller_id,
                'reason' => $validated['failure_reason'],
            ],
            request: $request,
        );

        return app(SuccessResponse::class, [
            'data' => $withdrawal,
            'message' => 'Withdrawal marked as failed.',
        ]);
    }
}
