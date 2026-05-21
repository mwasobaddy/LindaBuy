<?php

use App\Models\CallbackIdempotency;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MpesaService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->user = User::factory()->create(['phone' => '700000001']);
    $this->user->assignRole('buyer');
});

test('validate callback skips in sandbox without secret', function () {
    Config::set('mpesa.callback_hmac_secret', '');
    Config::set('mpesa.environment', 'sandbox');

    $service = app(MpesaService::class);

    expect($service->validateCallback(['Body' => ['stkCallback' => []]]))->toBeTrue();
});

test('validate callback rejects empty payload', function () {
    Config::set('mpesa.callback_hmac_secret', 'test_secret');
    Config::set('mpesa.environment', 'production');

    $service = app(MpesaService::class);

    expect($service->validateCallback([]))->toBeFalse();
});

test('validate callback rejects missing signature', function () {
    Config::set('mpesa.callback_hmac_secret', 'test_secret');
    Config::set('mpesa.environment', 'production');

    $service = app(MpesaService::class);

    expect($service->validateCallback(['Body' => ['stkCallback' => ['ResultCode' => 0]]]))->toBeFalse();
});

test('validate callback accepts valid signature from stkCallback', function () {
    $secret = 'test_secret';
    Config::set('mpesa.callback_hmac_secret', $secret);
    Config::set('mpesa.environment', 'production');

    $payload = [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_123',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ],
        ],
    ];

    $signature = hash_hmac('sha256', json_encode($payload), $secret);
    $payload['Body']['stkCallback']['Signature'] = $signature;

    $service = app(MpesaService::class);

    expect($service->validateCallback($payload))->toBeTrue();
});

test('validate callback rejects invalid signature', function () {
    $secret = 'test_secret';
    Config::set('mpesa.callback_hmac_secret', $secret);
    Config::set('mpesa.environment', 'production');

    $payload = [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_123',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'Signature' => 'invalid_signature_value',
            ],
        ],
    ];

    $service = app(MpesaService::class);

    expect($service->validateCallback($payload))->toBeFalse();
});

test('validate callback accepts valid signature from reversal result payload', function () {
    $secret = 'test_secret';
    Config::set('mpesa.callback_hmac_secret', $secret);
    Config::set('mpesa.environment', 'production');

    $payload = [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'TransactionID' => 'NFC4JN6Q1G',
        ],
    ];

    $signature = hash_hmac('sha256', json_encode($payload), $secret);
    $payload['Result']['Signature'] = $signature;

    $service = app(MpesaService::class);

    expect($service->validateCallback($payload))->toBeTrue();
});

test('validate callback rejects in production without secret', function () {
    Config::set('mpesa.callback_hmac_secret', '');
    Config::set('mpesa.environment', 'production');

    $service = app(MpesaService::class);

    expect($service->validateCallback(['Body' => ['stkCallback' => []]]))->toBeFalse();
});

test('process callback allows amount mismatch in sandbox', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseCallback')
            ->once()
            ->andReturn([
                'checkout_request_id' => 'ws_CO_AMT_SANDBOX',
                'result_code' => 0,
                'result_desc' => 'Success',
                'Amount' => 999,
                'MpesaReceiptNumber' => 'TXN_AMT_SB',
                'PhoneNumber' => 254708374149,
            ]);
    });

    $callback = CallbackIdempotency::create([
        'correlation_id' => 'ws_CO_AMT_SANDBOX',
        'checkout_request_id' => 'ws_CO_AMT_SANDBOX',
        'user_id' => $this->user->id,
        'amount' => 50000,
        'phone' => '700000001',
        'reference' => 'TOPUP_1_123',
        'status' => 'pending',
    ]);

    $this->postJson('/api/wallet/callback', [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_AMT_SANDBOX',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 999],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'TXN_AMT_SB'],
                        ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                    ],
                ],
            ],
        ],
    ]);

    $this->assertDatabaseHas('callback_idempotency', [
        'id' => $callback->id,
        'status' => 'success',
    ]);

    $transactions = Transaction::where('transaction_type', 'deposit')->count();
    expect($transactions)->toBe(1);
});

test('reversal result callback processes success', function () {
    $seller = Seller::factory()->create(['user_id' => $this->user->id]);
    $order = Order::factory()->create([
        'buyer_id' => $this->user->id,
        'seller_id' => $seller->id,
        'status' => 'funds_locked',
        'price' => 100000,
        'mpesa_transaction_id' => 'ORIG_TXN_001',
        'flat_fee' => 5000,
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseReversalResult')
            ->once()
            ->andReturn([
                'result_code' => 0,
                'result_desc' => 'Success',
                'transaction_id' => 'ORIG_TXN_001',
                'amount' => 1000,
            ]);
    });

    $this->postJson('/api/wallet/reversal-result', [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'TransactionID' => 'ORIG_TXN_001',
            'Amount' => 1000,
        ],
    ])->assertJson(['ResultCode' => 0]);

    $order->refresh();
    expect($order->status)->toBe('cancelled');
    expect($order->reversal_failed_at)->toBeNull();
});

test('reversal result callback handles failure', function () {
    $seller = Seller::factory()->create(['user_id' => $this->user->id]);
    $order = Order::factory()->create([
        'buyer_id' => $this->user->id,
        'seller_id' => $seller->id,
        'status' => 'funds_locked',
        'price' => 100000,
        'mpesa_transaction_id' => 'ORIG_TXN_FAIL',
        'flat_fee' => 5000,
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseReversalResult')
            ->once()
            ->andReturn([
                'result_code' => 1,
                'result_desc' => 'Reversal failed',
                'transaction_id' => 'ORIG_TXN_FAIL',
                'amount' => 1000,
            ]);
    });

    $this->postJson('/api/wallet/reversal-result', [
        'Result' => [
            'ResultCode' => 1,
            'ResultDesc' => 'Reversal failed',
            'TransactionID' => 'ORIG_TXN_FAIL',
            'Amount' => 1000,
        ],
    ])->assertJson(['ResultCode' => 0]);

    $order->refresh();
    expect($order->reversal_failed_at)->not->toBeNull();
    expect($order->reversal_failure_reason)->toBe('Reversal failed');
    expect($order->reversal_attempts)->toBe(1);
});

test('reversal result callback ignores unknown transaction id', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseReversalResult')
            ->once()
            ->andReturn([
                'result_code' => 0,
                'result_desc' => 'Success',
                'transaction_id' => 'UNKNOWN_TXN',
                'amount' => 1000,
            ]);
    });

    $this->postJson('/api/wallet/reversal-result', [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'TransactionID' => 'UNKNOWN_TXN',
            'Amount' => 1000,
        ],
    ])->assertJson(['ResultCode' => 0]);
});

test('reversal timeout callback schedules retry', function () {
    $seller = Seller::factory()->create(['user_id' => $this->user->id]);
    $order = Order::factory()->create([
        'buyer_id' => $this->user->id,
        'seller_id' => $seller->id,
        'status' => 'funds_locked',
        'price' => 100000,
        'mpesa_transaction_id' => 'ORIG_TXN_TIMEOUT',
        'flat_fee' => 5000,
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseReversalTimeout')
            ->once()
            ->andReturn([
                'result_code' => null,
                'result_desc' => 'Request timeout',
                'transaction_id' => 'ORIG_TXN_TIMEOUT',
            ]);
    });

    $this->postJson('/api/wallet/reversal-timeout', [
        'Result' => [
            'ResultCode' => null,
            'ResultDesc' => 'Request timeout',
            'TransactionID' => 'ORIG_TXN_TIMEOUT',
        ],
    ])->assertJson(['ResultCode' => 0]);

    $order->refresh();
    expect($order->reversal_attempts)->toBe(1);
    expect($order->reversal_retry_at)->not->toBeNull();
    expect($order->reversal_failure_reason)->toBe('Request timeout');
});

test('reversal callback duplicate processing is guarded', function () {
    $seller = Seller::factory()->create(['user_id' => $this->user->id]);
    $order = Order::factory()->create([
        'buyer_id' => $this->user->id,
        'seller_id' => $seller->id,
        'status' => 'cancelled',
        'price' => 100000,
        'mpesa_transaction_id' => 'ORIG_TXN_DUP',
        'flat_fee' => 5000,
        'reversal_resolved_at' => now(),
        'reversal_resolution_type' => 'reversal',
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('parseReversalResult')
            ->once()
            ->andReturn([
                'result_code' => 0,
                'result_desc' => 'Success',
                'transaction_id' => 'ORIG_TXN_DUP',
                'amount' => 1000,
            ]);
    });

    $this->postJson('/api/wallet/reversal-result', [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'TransactionID' => 'ORIG_TXN_DUP',
            'Amount' => 1000,
        ],
    ])->assertJson(['ResultCode' => 0]);

    $transactions = Transaction::where('transaction_type', 'reversal')
        ->where('reference_id', $order->id)
        ->count();
    expect($transactions)->toBe(0);
});

test('reversal callback rejected with invalid hmac', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(false);
    });

    $this->postJson('/api/wallet/reversal-result', [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'TransactionID' => 'TXN_WITH_BAD_HMAC',
        ],
    ])->assertStatus(400);
});

test('reversal timeout callback rejected with invalid hmac', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateCallback')
            ->once()
            ->andReturn(false);
    });

    $this->postJson('/api/wallet/reversal-timeout', [
        'Result' => [
            'ResultCode' => null,
            'ResultDesc' => 'Request timeout',
            'TransactionID' => 'TXN_WITH_BAD_HMAC',
        ],
    ])->assertStatus(400);
});

test('parse reversal result extracts correct fields', function () {
    Config::set('mpesa.callback_hmac_secret', '');
    Config::set('mpesa.environment', 'sandbox');

    $service = app(MpesaService::class);

    $payload = [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'TransactionID' => 'NFC4JN6Q1G',
            'Amount' => 1000,
        ],
    ];

    $parsed = $service->parseReversalResult($payload);

    expect($parsed['result_code'])->toBe(0);
    expect($parsed['result_desc'])->toBe('Success');
    expect($parsed['transaction_id'])->toBe('NFC4JN6Q1G');
    expect($parsed['amount'])->toBe(1000);
});

test('parse reversal timeout extracts correct fields', function () {
    Config::set('mpesa.callback_hmac_secret', '');
    Config::set('mpesa.environment', 'sandbox');

    $service = app(MpesaService::class);

    $payload = [
        'Result' => [
            'ResultCode' => null,
            'ResultDesc' => 'Request timeout',
            'TransactionID' => 'TXN_TIMEOUT_1',
        ],
    ];

    $parsed = $service->parseReversalTimeout($payload);

    expect($parsed['result_code'])->toBeNull();
    expect($parsed['result_desc'])->toBe('Request timeout');
    expect($parsed['transaction_id'])->toBe('TXN_TIMEOUT_1');
});
