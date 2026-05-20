<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Entry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function recordDeposit(User $user, int $amountCents, string $reference, string $description): Transaction
    {
        return DB::transaction(function () use ($user, $amountCents, $description) {
            $mpesaFloat = Account::where('account_code', 'MPESA_FLOAT')
                ->lockForUpdate()
                ->firstOrFail();

            $buyerAccount = $this->getOrCreateBuyerWalletAccount($user);

            $transaction = Transaction::create([
                'transaction_type' => 'deposit',
                'reference_type' => 'top_up',
                'reference_id' => null,
                'description' => $description,
                'status' => 'completed',
            ]);

            $lastMpesaEntry = Entry::where('account_id', $mpesaFloat->id)
                ->orderBy('id', 'desc')
                ->first();
            $mpesaPreviousBalance = $lastMpesaEntry ? $lastMpesaEntry->balance_after : 0;

            $lastBuyerEntry = Entry::where('account_id', $buyerAccount->id)
                ->orderBy('id', 'desc')
                ->first();
            $buyerPreviousBalance = $lastBuyerEntry ? $lastBuyerEntry->balance_after : 0;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $mpesaFloat->id,
                'debit_amount' => $amountCents,
                'credit_amount' => 0,
                'balance_after' => $mpesaPreviousBalance + $amountCents,
            ]);

            $buyerBalanceAfter = $buyerAccount->normal_balance === 'credit'
                ? $buyerPreviousBalance + $amountCents
                : $buyerPreviousBalance - $amountCents;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $buyerAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amountCents,
                'balance_after' => $buyerBalanceAfter,
            ]);

            return $transaction;
        });
    }

    public function getBuyerBalance(User $user): int
    {
        $account = $this->getOrCreateBuyerWalletAccount($user);

        return $account->balance;
    }

    public function getAccountHistory(Account $account, int $limit = 50): Collection
    {
        return Entry::with('transaction')
            ->where('account_id', $account->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getOrCreateBuyerWalletAccount(User $user): Account
    {
        $code = 'BUYER_WALLET_'.$user->id;

        return Account::firstOrCreate(
            ['account_code' => $code],
            [
                'account_type' => 'liability',
                'account_name' => 'Buyer Wallet: '.$user->id,
                'normal_balance' => 'credit',
            ]
        );
    }

    public function getOrCreateSellerReceivableAccount($seller): Account
    {
        $code = 'SELLER_RECEIVABLE_'.$seller->id;

        return Account::firstOrCreate(
            ['account_code' => $code],
            [
                'account_type' => 'liability',
                'account_name' => 'Seller Receivable: '.$seller->id,
                'normal_balance' => 'credit',
            ]
        );
    }
}
