<?php

use App\Models\Account;
use App\Models\Entry;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);
});

function createSellerWithReceivable(int $amountCents): array
{
    $user = User::factory()->create(['role' => 'seller:approved']);
    $user->assignRole('seller');

    $seller = Seller::create([
        'user_id' => $user->id,
        'shop_name' => 'Test Shop',
        'verification_status' => 'APPROVED',
    ]);

    $account = Account::create([
        'account_type' => 'liability',
        'account_name' => 'Seller Receivable: '.$seller->id,
        'account_code' => 'SELLER_RECEIVABLE_'.$seller->id,
        'normal_balance' => 'credit',
    ]);

    $transaction = Transaction::create([
        'transaction_type' => 'escrow_release',
        'description' => 'Test receivable',
        'status' => 'completed',
    ]);

    Entry::create([
        'transaction_id' => $transaction->id,
        'account_id' => $account->id,
        'debit_amount' => 0,
        'credit_amount' => $amountCents,
        'balance_after' => $amountCents,
    ]);

    return ['user' => $user, 'seller' => $seller];
}

function createAdmin(): User
{
    $admin = User::factory()->create(['role' => 'buyer']);
    $admin->assignRole('admin');

    return $admin;
}

test('seller can request withdrawal', function () {
    $data = createSellerWithReceivable(100000);
    $user = $data['user'];

    $response = $this
        ->actingAs($user)
        ->postJson('/api/withdrawals/request', [
            'amount_cents' => 50000,
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'amount', 'status'],
    ]);

    $this->assertDatabaseHas('withdrawals', [
        'seller_id' => $data['seller']->id,
        'amount' => 50000,
        'status' => 'pending',
    ]);
});

test('request fails for insufficient receivable balance', function () {
    $data = createSellerWithReceivable(10000);
    $user = $data['user'];

    $response = $this
        ->actingAs($user)
        ->postJson('/api/withdrawals/request', [
            'amount_cents' => 50000,
        ]);

    $response->assertStatus(422);
});

test('request fails for non-seller users', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $response = $this
        ->actingAs($user)
        ->postJson('/api/withdrawals/request', [
            'amount_cents' => 50000,
        ]);

    $response->assertStatus(403);
});

test('admin can process a withdrawal', function () {
    $data = createSellerWithReceivable(100000);
    $admin = createAdmin();

    $withdrawal = Withdrawal::create([
        'seller_id' => $data['seller']->id,
        'amount' => 50000,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->postJson("/api/admin/withdrawals/{$withdrawal->id}/process");

    $response->assertOk();
    $this->assertDatabaseHas('withdrawals', [
        'id' => $withdrawal->id,
        'status' => 'processing',
    ]);
});

test('admin can complete a withdrawal', function () {
    $data = createSellerWithReceivable(100000);
    $admin = createAdmin();

    $withdrawal = Withdrawal::create([
        'seller_id' => $data['seller']->id,
        'amount' => 50000,
        'status' => 'processing',
        'requested_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->postJson("/api/admin/withdrawals/{$withdrawal->id}/complete");

    $response->assertOk();
    $this->assertDatabaseHas('withdrawals', [
        'id' => $withdrawal->id,
        'status' => 'completed',
    ]);
});

test('admin can mark withdrawal as failed with reason', function () {
    $data = createSellerWithReceivable(100000);
    $admin = createAdmin();

    $withdrawal = Withdrawal::create([
        'seller_id' => $data['seller']->id,
        'amount' => 50000,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->postJson("/api/admin/withdrawals/{$withdrawal->id}/fail", [
            'failure_reason' => 'Bank details incorrect',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('withdrawals', [
        'id' => $withdrawal->id,
        'status' => 'failed',
        'failure_reason' => 'Bank details incorrect',
    ]);
});

test('completing withdrawal debits seller receivable', function () {
    $data = createSellerWithReceivable(100000);
    $admin = createAdmin();

    $withdrawal = Withdrawal::create([
        'seller_id' => $data['seller']->id,
        'amount' => 40000,
        'status' => 'processing',
        'requested_at' => now(),
    ]);

    $receivableAccount = Account::byCode('SELLER_RECEIVABLE_'.$data['seller']->id)->first();
    $balanceBefore = $receivableAccount->balance;

    expect($balanceBefore)->toBe(100000);

    $this->actingAs($admin)->postJson("/api/admin/withdrawals/{$withdrawal->id}/complete");

    $receivableAccount->refresh();
    expect($receivableAccount->balance)->toBe(60000);
});
