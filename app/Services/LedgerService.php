<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Entry;
use App\Models\Order;
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

    public function recordEscrowLock(User $user, int $amountCents, Order $order): Transaction
    {
        return DB::transaction(function () use ($amountCents, $order) {
            $mpesaFloat = Account::where('account_code', 'MPESA_FLOAT')
                ->lockForUpdate()
                ->firstOrFail();

            $escrowHolding = Account::where('account_code', 'ESCROW_HOLDING')
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = Transaction::create([
                'transaction_type' => 'escrow_lock',
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => 'Escrow lock for order #'.$order->id,
                'status' => 'completed',
            ]);

            $lastMpesaEntry = Entry::where('account_id', $mpesaFloat->id)
                ->orderBy('id', 'desc')
                ->first();
            $mpesaPreviousBalance = $lastMpesaEntry ? $lastMpesaEntry->balance_after : 0;

            $lastEscrowEntry = Entry::where('account_id', $escrowHolding->id)
                ->orderBy('id', 'desc')
                ->first();
            $escrowPreviousBalance = $lastEscrowEntry ? $lastEscrowEntry->balance_after : 0;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $mpesaFloat->id,
                'debit_amount' => $amountCents,
                'credit_amount' => 0,
                'balance_after' => $mpesaPreviousBalance - $amountCents,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $escrowHolding->id,
                'debit_amount' => 0,
                'credit_amount' => $amountCents,
                'balance_after' => $escrowPreviousBalance + $amountCents,
            ]);

            return $transaction;
        });
    }

    public function recordEscrowRelease(Order $order): Transaction
    {
        return DB::transaction(function () use ($order) {
            $escrowHolding = Account::where('account_code', 'ESCROW_HOLDING')
                ->lockForUpdate()
                ->firstOrFail();

            $sellerAccount = $this->getOrCreateSellerReceivableAccount($order->seller);

            $feesOwner = Account::where('account_code', 'PLATFORM_FEES_OWNER')
                ->lockForUpdate()
                ->firstOrFail();

            $feesDeveloper = Account::where('account_code', 'PLATFORM_FEES_DEVELOPER')
                ->lockForUpdate()
                ->firstOrFail();

            $amountCents = $order->price;
            $flatFee = $order->flat_fee;
            $sellerAmount = $amountCents - $flatFee;
            $ownerShare = (int) ($flatFee * 0.7);
            $developerShare = $flatFee - $ownerShare;

            $transaction = Transaction::create([
                'transaction_type' => 'escrow_release',
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => 'Escrow release for order #'.$order->id,
                'status' => 'completed',
            ]);

            $lastEscrowEntry = Entry::where('account_id', $escrowHolding->id)
                ->orderBy('id', 'desc')
                ->first();
            $escrowPreviousBalance = $lastEscrowEntry ? $lastEscrowEntry->balance_after : 0;

            $lastSellerEntry = Entry::where('account_id', $sellerAccount->id)
                ->orderBy('id', 'desc')
                ->first();
            $sellerPreviousBalance = $lastSellerEntry ? $lastSellerEntry->balance_after : 0;

            $lastOwnerEntry = Entry::where('account_id', $feesOwner->id)
                ->orderBy('id', 'desc')
                ->first();
            $ownerPreviousBalance = $lastOwnerEntry ? $lastOwnerEntry->balance_after : 0;

            $lastDeveloperEntry = Entry::where('account_id', $feesDeveloper->id)
                ->orderBy('id', 'desc')
                ->first();
            $developerPreviousBalance = $lastDeveloperEntry ? $lastDeveloperEntry->balance_after : 0;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $escrowHolding->id,
                'debit_amount' => $amountCents,
                'credit_amount' => 0,
                'balance_after' => $escrowPreviousBalance - $amountCents,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $sellerAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $sellerAmount,
                'balance_after' => $sellerPreviousBalance + $sellerAmount,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $feesOwner->id,
                'debit_amount' => 0,
                'credit_amount' => $ownerShare,
                'balance_after' => $ownerPreviousBalance + $ownerShare,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $feesDeveloper->id,
                'debit_amount' => 0,
                'credit_amount' => $developerShare,
                'balance_after' => $developerPreviousBalance + $developerShare,
            ]);

            return $transaction;
        });
    }

    public function recordWriteOff(Order $order, int $amountCents): Transaction
    {
        return DB::transaction(function () use ($order, $amountCents) {
            $escrowHolding = Account::where('account_code', 'ESCROW_HOLDING')
                ->lockForUpdate()
                ->firstOrFail();

            $reversalLoss = Account::where('account_code', 'REVERSAL_LOSS')
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = Transaction::create([
                'transaction_type' => 'write_off',
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => 'Write off for order #'.$order->id,
                'status' => 'completed',
            ]);

            $lastEscrowEntry = Entry::where('account_id', $escrowHolding->id)
                ->orderBy('id', 'desc')
                ->first();
            $escrowPreviousBalance = $lastEscrowEntry ? $lastEscrowEntry->balance_after : 0;

            $lastLossEntry = Entry::where('account_id', $reversalLoss->id)
                ->orderBy('id', 'desc')
                ->first();
            $lossPreviousBalance = $lastLossEntry ? $lastLossEntry->balance_after : 0;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $reversalLoss->id,
                'debit_amount' => $amountCents,
                'credit_amount' => 0,
                'balance_after' => $lossPreviousBalance + $amountCents,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $escrowHolding->id,
                'debit_amount' => $amountCents,
                'credit_amount' => 0,
                'balance_after' => $escrowPreviousBalance - $amountCents,
            ]);

            return $transaction;
        });
    }

    public function recordReversal(Order $order, int $amountCents): Transaction
    {
        return DB::transaction(function () use ($order, $amountCents) {
            $escrowHolding = Account::where('account_code', 'ESCROW_HOLDING')
                ->lockForUpdate()
                ->firstOrFail();

            $mpesaFloat = Account::where('account_code', 'MPESA_FLOAT')
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = Transaction::create([
                'transaction_type' => 'reversal',
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => 'Reversal for order #'.$order->id,
                'status' => 'completed',
            ]);

            $lastEscrowEntry = Entry::where('account_id', $escrowHolding->id)
                ->orderBy('id', 'desc')
                ->first();
            $escrowPreviousBalance = $lastEscrowEntry ? $lastEscrowEntry->balance_after : 0;

            $lastMpesaEntry = Entry::where('account_id', $mpesaFloat->id)
                ->orderBy('id', 'desc')
                ->first();
            $mpesaPreviousBalance = $lastMpesaEntry ? $lastMpesaEntry->balance_after : 0;

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $escrowHolding->id,
                'debit_amount' => $amountCents,
                'credit_amount' => 0,
                'balance_after' => $escrowPreviousBalance - $amountCents,
            ]);

            Entry::create([
                'transaction_id' => $transaction->id,
                'account_id' => $mpesaFloat->id,
                'debit_amount' => 0,
                'credit_amount' => $amountCents,
                'balance_after' => $mpesaPreviousBalance + $amountCents,
            ]);

            return $transaction;
        });
    }
}
