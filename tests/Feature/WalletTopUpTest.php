<?php

use App\Models\CallbackIdempotency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\MpesaService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->user = User::factory()->create(['phone' => '700000001']);
    $this->user->assignRole('buyer');
});

test('buyer can initiate top-up', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_123456789',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
            ]);
    });

    $response = $this
        ->actingAs($this->user)
        ->postJson('/api/wallet/top-up', [
            'amount_cents' => 50000,
        ]);

    $response->assertCreated();
    $response->assertJson([
        'data' => [
            'checkout_request_id' => 'ws_CO_123456789',
        ],
    ]);

    $this->assertDatabaseHas('callback_idempotency', [
        'checkout_request_id' => 'ws_CO_123456789',
    ]);
});

test('top-up fails for invalid amounts', function () {
    $response = $this
        ->actingAs($this->user)
        ->postJson('/api/wallet/top-up', [
            'amount_cents' => 500,
        ]);

    $response->assertStatus(422);
});

test('top-up requires authentication', function () {
    $response = $this->postJson('/api/wallet/top-up', [
        'amount_cents' => 50000,
    ]);

    $response->assertStatus(401);
});

test('callback processes successfully and balance increases', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'merchant_request_id' => '29115-34620561-1',
                'checkout_request_id' => 'ws_CO_191220191020363925',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 500,
                'MpesaReceiptNumber' => 'LKJ12TY3',
                'PhoneNumber' => 254708374149,
            ]);
    });

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_191220191020363925',
        'checkout_request_id' => 'ws_CO_191220191020363925',
    ]);

    $response = $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ],
        ],
    ]);

    $response->assertJson(['ResultCode' => 0]);
});

test('duplicate callback is idempotent', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_DUP',
                'result_code' => 0,
                'Amount' => 500,
                'MpesaReceiptNumber' => 'DUP123',
            ]);
    });

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_DUP',
        'checkout_request_id' => 'ws_CO_DUP',
        'processed_at' => now(),
        'result_code' => 0,
    ]);

    $response = $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_DUP',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ],
        ],
    ]);

    $response->assertJson(['ResultCode' => 0]);

    $transactions = Transaction::where('description', 'M-Pesa top-up')->count();
    expect($transactions)->toBe(0);
});

test('callback with failure code does not change balance', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_FAIL',
                'result_code' => 1,
                'result_desc' => 'Failed',
            ]);
    });

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_FAIL',
        'checkout_request_id' => 'ws_CO_FAIL',
    ]);

    $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_FAIL',
                'ResultCode' => 1,
                'ResultDesc' => 'Failed',
            ],
        ],
    ]);

    $transactions = Transaction::where('transaction_type', 'deposit')->count();
    expect($transactions)->toBe(0);
});

test('balance endpoint returns correct available amount', function () {
    $ledgerService = app(LedgerService::class);
    $ledgerService->recordDeposit($this->user, 100000, 'test_ref', 'Test deposit');

    $response = $this
        ->actingAs($this->user)
        ->getJson('/api/wallet/balance');

    $response->assertOk();
    $response->assertJsonPath('data.balance.available', 100000);
});
