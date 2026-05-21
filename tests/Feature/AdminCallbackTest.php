<?php

use App\Models\CallbackIdempotency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MpesaService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::factory()->create(['phone' => '700000001']);
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create(['phone' => '700000002']);
    $this->user->assignRole('buyer');
});

test('initiate top-up stores user_id amount phone reference and status', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_TRACK_123',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
            ]);
    });

    $this->actingAs($this->user)
        ->postJson('/api/wallet/top-up', ['amount_cents' => 50000])
        ->assertCreated();

    $record = CallbackIdempotency::where('checkout_request_id', 'ws_CO_TRACK_123')->first();

    expect($record)->not->toBeNull();
    expect($record->user_id)->toBe($this->user->id);
    expect($record->amount)->toBe(50000);
    expect($record->phone)->toBe('700000002');
    expect($record->reference)->toStartWith('TOPUP_'.$this->user->id.'_');
    expect($record->status)->toBe('pending');
});

test('successful callback sets status success and stores mpesa_receipt', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'merchant_request_id' => '29115-34620561-1',
                'checkout_request_id' => 'ws_CO_SUCCESS_1',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 500,
                'MpesaReceiptNumber' => 'NFC4JN6Q1G',
                'PhoneNumber' => 254708374149,
            ]);
    });

    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_SUCCESS_1',
        'checkout_request_id' => 'ws_CO_SUCCESS_1',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'phone' => '700000002',
        'reference' => 'TOPUP_1_123',
        'status' => 'pending',
    ]);

    $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_SUCCESS_1',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 500],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NFC4JN6Q1G'],
                        ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                    ],
                ],
            ],
        ],
    ]);

    $this->assertDatabaseHas('callback_idempotency', [
        'id' => $callback->id,
        'status' => 'success',
        'result_code' => 0,
        'mpesa_receipt' => 'NFC4JN6Q1G',
    ]);

    $callback->refresh();
    expect($callback->response_description)->toBe('Success');
    expect($callback->callback_payload)->not->toBeNull();
});

test('failed callback sets status failed and stores response_description', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_FAIL_1',
                'result_code' => 1,
                'result_desc' => 'The balance is insufficient',
            ]);
    });

    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_FAIL_1',
        'checkout_request_id' => 'ws_CO_FAIL_1',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => 'pending',
    ]);

    $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_FAIL_1',
                'ResultCode' => 1,
                'ResultDesc' => 'The balance is insufficient',
            ],
        ],
    ]);

    $this->assertDatabaseHas('callback_idempotency', [
        'id' => $callback->id,
        'status' => 'failed',
        'result_code' => 1,
        'response_description' => 'The balance is insufficient',
    ]);

    $transactions = Transaction::where('transaction_type', 'deposit')->count();
    expect($transactions)->toBe(0);
});

test('findUserByCheckoutRequest returns user after callback processing', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_FIND_USER',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 500,
                'MpesaReceiptNumber' => 'TXN12345',
                'PhoneNumber' => 254708374149,
            ]);
    });

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_FIND_USER',
        'checkout_request_id' => 'ws_CO_FIND_USER',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'phone' => '700000002',
        'reference' => 'TOPUP_2_123',
        'status' => 'pending',
    ]);

    $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_FIND_USER',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 500],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'TXN12345'],
                        ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                    ],
                ],
            ],
        ],
    ]);

    $transactions = Transaction::where('transaction_type', 'deposit')->count();
    expect($transactions)->toBe(1);
});

test('admin can view callback list', function () {
    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_LIST_1',
        'checkout_request_id' => 'ws_CO_LIST_1',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => 'pending',
    ]);

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_LIST_2',
        'checkout_request_id' => 'ws_CO_LIST_2',
        'user_id' => $this->user->id,
        'amount' => 100000,
        'status' => 'success',
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/callbacks');

    $response->assertOk();
    $response->assertJsonCount(2, 'data.data');
});

test('admin cannot view callbacks without permission', function () {
    $nonAdmin = User::factory()->create(['phone' => '700000003']);
    $nonAdmin->assignRole('buyer');

    $response = $this
        ->actingAs($nonAdmin)
        ->getJson('/api/admin/callbacks');

    $response->assertStatus(403);
});

test('admin can filter callbacks by status', function () {
    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_FILTER_1',
        'checkout_request_id' => 'ws_CO_FILTER_1',
        'user_id' => $this->user->id,
        'status' => 'success',
    ]);

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_FILTER_2',
        'checkout_request_id' => 'ws_CO_FILTER_2',
        'user_id' => $this->user->id,
        'status' => 'failed',
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/callbacks?status=failed');

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.status'))->toBe('failed');
});

test('admin can filter callbacks by date range', function () {
    $callback = new CallbackIdempotency;
    $callback->correlation_id = 'ws_CO_DATE_1';
    $callback->checkout_request_id = 'ws_CO_DATE_1';
    $callback->user_id = $this->user->id;
    $callback->status = 'pending';
    $callback->created_at = now()->subDays(5);
    $callback->save();

    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/callbacks?date_from='.now()->subDays(1)->toDateString());

    $response->assertOk();
    expect(collect($response->json('data.data')))->toHaveCount(0);
});

test('admin can filter callbacks by phone', function () {
    $otherUser = User::factory()->create(['phone' => '700000099']);

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_PHONE_1',
        'checkout_request_id' => 'ws_CO_PHONE_1',
        'user_id' => $this->user->id,
        'phone' => '700000002',
        'status' => 'pending',
    ]);

    CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_PHONE_2',
        'checkout_request_id' => 'ws_CO_PHONE_2',
        'user_id' => $otherUser->id,
        'phone' => '700000099',
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/callbacks?phone=700000002');

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(1);
});

test('admin can view single callback detail', function () {
    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_DETAIL_1',
        'checkout_request_id' => 'ws_CO_DETAIL_1',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => 'pending',
        'callback_payload' => ['Body' => ['stkCallback' => ['ResultCode' => 0]]],
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->getJson("/api/admin/callbacks/{$callback->id}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($callback->id);
    expect($response->json('data.callback_payload'))->not->toBeNull();
});

test('admin can retry failed callback', function () {
    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_RETRY_FAILED',
        'checkout_request_id' => 'ws_CO_RETRY_FAILED',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => 'failed',
        'result_code' => 1,
        'response_description' => 'Request cancelled by user',
        'processed_at' => now()->subMinutes(5),
        'callback_payload' => [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_RETRY_FAILED',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 500],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RETRY_TXN_1'],
                            ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_RETRY_FAILED',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 500,
                'MpesaReceiptNumber' => 'RETRY_TXN_1',
                'PhoneNumber' => 254708374149,
            ]);
    });

    $response = $this
        ->actingAs($this->admin)
        ->postJson("/api/admin/callbacks/{$callback->id}/retry");

    $response->assertOk();
    $callback->refresh();

    expect($callback->retry_count)->toBe(1);
    expect($callback->last_retried_at)->not->toBeNull();
    expect($callback->status)->toBe('success');
});

test('admin can retry timed_out callback', function () {
    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_TIMEOUT',
        'checkout_request_id' => 'ws_CO_TIMEOUT',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => 'timed_out',
        'created_at' => now()->subMinutes(30),
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkQuery')
            ->once()
            ->andReturn([
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_TIMEOUT',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ]);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_TIMEOUT',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 500,
                'MpesaReceiptNumber' => 'TXN_TIMEOUT',
                'PhoneNumber' => 254708374149,
            ]);
    });

    $response = $this
        ->actingAs($this->admin)
        ->postJson("/api/admin/callbacks/{$callback->id}/retry");

    $response->assertOk();
    $callback->refresh();

    expect($callback->retry_count)->toBe(1);
    expect($callback->last_retried_at)->not->toBeNull();
});

test('admin cannot retry successful callback', function () {
    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_ALREADY_OK',
        'checkout_request_id' => 'ws_CO_ALREADY_OK',
        'status' => 'success',
        'result_code' => 0,
        'processed_at' => now(),
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->postJson("/api/admin/callbacks/{$callback->id}/retry");

    $response->assertStatus(422);
});

test('admin cannot retry without permission', function () {
    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_NO_PERM',
        'checkout_request_id' => 'ws_CO_NO_PERM',
        'status' => 'failed',
    ]);

    $nonAdmin = User::factory()->create(['phone' => '700000004']);
    $nonAdmin->assignRole('buyer');

    $response = $this
        ->actingAs($nonAdmin)
        ->postJson("/api/admin/callbacks/{$callback->id}/retry");

    $response->assertStatus(403);
});

test('stale callback detection command marks timed_out', function () {
    $callback = new CallbackIdempotency;
    $callback->correlation_id = 'ws_CO_STALE_1';
    $callback->checkout_request_id = 'ws_CO_STALE_1';
    $callback->user_id = $this->user->id;
    $callback->status = 'pending';
    $callback->created_at = now()->subMinutes(30);
    $callback->save();

    $this->artisan('mpesa:detect-stale-callbacks')->assertSuccessful();

    $callback->refresh();
    expect($callback->status)->toBe('timed_out');
});

test('stale callback detection skips recent pending callbacks', function () {
    $callback = new CallbackIdempotency;
    $callback->correlation_id = 'ws_CO_FRESH_1';
    $callback->checkout_request_id = 'ws_CO_FRESH_1';
    $callback->user_id = $this->user->id;
    $callback->status = 'pending';
    $callback->created_at = now()->subMinutes(2);
    $callback->save();

    $this->artisan('mpesa:detect-stale-callbacks')->assertSuccessful();

    $callback->refresh();
    expect($callback->status)->toBe('pending');
});

test('retry increments retry_count', function () {
    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_RETRY_COUNT',
        'checkout_request_id' => 'ws_CO_RETRY_COUNT',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => 'failed',
        'result_code' => 1,
        'processed_at' => now()->subMinutes(5),
        'callback_payload' => [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_RETRY_COUNT',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 500],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RETRY_COUNT_TXN'],
                            ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_RETRY_COUNT',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 500,
                'MpesaReceiptNumber' => 'RETRY_COUNT_TXN',
                'PhoneNumber' => 254708374149,
            ]);
    });

    $this->actingAs($this->admin)
        ->postJson("/api/admin/callbacks/{$callback->id}/retry")
        ->assertOk();

    $callback->refresh();
    expect($callback->retry_count)->toBe(1);
    expect($callback->last_retried_at)->not->toBeNull();
});
