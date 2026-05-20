<?php

use App\Models\Account;
use App\Models\Entry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->ledgerService = app(LedgerService::class);
});

test('can record a deposit and verify balance changes', function () {
    $user = User::factory()->create();

    $transaction = $this->ledgerService->recordDeposit($user, 50000, 'ref_001', 'Test deposit');

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($transaction->status)->toBe('completed');
    expect($transaction->entries)->toHaveCount(2);

    $balance = $this->ledgerService->getBuyerBalance($user);
    expect($balance)->toBe(50000);
});

test('deposit creates correct debit and credit entries', function () {
    $user = User::factory()->create();

    $transaction = $this->ledgerService->recordDeposit($user, 50000, 'ref_002', 'Test deposit');

    $entries = $transaction->entries;
    $totalDebits = $entries->sum('debit_amount');
    $totalCredits = $entries->sum('credit_amount');

    expect($totalDebits)->toBe($totalCredits);
    expect($totalDebits)->toBe(50000);

    $mpesaAccount = Account::byCode('MPESA_FLOAT')->first();
    $mpesaEntry = $entries->firstWhere('account_id', $mpesaAccount->id);
    expect($mpesaEntry->debit_amount)->toBe(50000);
    expect($mpesaEntry->credit_amount)->toBe(0);
});

test('balance after entry is correctly computed', function () {
    $user = User::factory()->create();

    $this->ledgerService->recordDeposit($user, 30000, 'ref_003', 'First deposit');
    $this->ledgerService->recordDeposit($user, 20000, 'ref_004', 'Second deposit');

    $account = $this->ledgerService->getOrCreateBuyerWalletAccount($user);
    $entries = Entry::where('account_id', $account->id)->orderBy('id')->get();

    expect($entries[0]->balance_after)->toBe(30000);
    expect($entries[1]->balance_after)->toBe(50000);
});

test('second deposit to same buyer updates balance correctly', function () {
    $user = User::factory()->create();

    $this->ledgerService->recordDeposit($user, 50000, 'ref_005', 'First deposit');
    $this->ledgerService->recordDeposit($user, 25000, 'ref_006', 'Second deposit');

    $balance = $this->ledgerService->getBuyerBalance($user);
    expect($balance)->toBe(75000);
});

test('retrieving history returns entries in reverse chronological order', function () {
    $user = User::factory()->create();

    $this->ledgerService->recordDeposit($user, 10000, 'ref_007', 'Deposit 1');
    $this->ledgerService->recordDeposit($user, 20000, 'ref_008', 'Deposit 2');
    $this->ledgerService->recordDeposit($user, 30000, 'ref_009', 'Deposit 3');

    $account = $this->ledgerService->getOrCreateBuyerWalletAccount($user);
    $history = $this->ledgerService->getAccountHistory($account);

    expect($history)->toHaveCount(3);
    expect($history[0]->balance_after)->toBe(60000);
    expect($history[2]->balance_after)->toBe(10000);
});
