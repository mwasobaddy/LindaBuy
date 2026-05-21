<?php

use App\Jobs\ExpireOrder;
use App\Jobs\ExpirePayment;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Services\OrderExpiryService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->sellerUser = User::factory()->create(['phone' => '700000001', 'role' => 'seller:approved']);
    $this->sellerUser->assignRole('seller');

    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'APPROVED',
    ]);

    $this->buyerUser = User::factory()->create(['phone' => '700000002', 'role' => 'buyer']);
    $this->buyerUser->assignRole('buyer');
});

test('expiry job marks pending_accept order as expired', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->subMinutes(5),
    ]);

    $job = new ExpireOrder($order);
    $job->handle(app(OrderExpiryService::class), app(AuditService::class));

    $order->refresh();
    $this->assertEquals('expired', $order->status);
});

test('expiry on funds_locked order marks cancelled', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->subMinutes(5),
    ]);

    $job = new ExpireOrder($order);
    $job->handle(app(OrderExpiryService::class), app(AuditService::class));

    $order->refresh();
    $this->assertEquals('cancelled', $order->status);
});

test('payment expiry job marks buyer-initiated order as payment_failed', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->addMinutes(5),
        'payment_expiry_at' => now()->subMinutes(1),
    ]);

    $job = new ExpirePayment($order);
    $job->handle(app(OrderExpiryService::class), app(AuditService::class));

    $order->refresh();
    $this->assertEquals('payment_failed', $order->status);
});

test('expired orders cannot be acted upon', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'expired',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->subMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertStatus(422);
});

test('expiry on funds_locked order sets reversal_failed_at when reversal throws', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->subMinutes(5),
    ]);

    $expiryService = app(OrderExpiryService::class);

    $ledgerService = Mockery::mock(LedgerService::class);
    $ledgerService->shouldReceive('recordReversal')
        ->once()
        ->andThrow(new RuntimeException('M-Pesa API unavailable'));

    $serviceWithMock = new OrderExpiryService($ledgerService, app(AuditService::class));

    $serviceWithMock->handleOrderExpiry($order);

    $order->refresh();
    expect($order->status)->toBe('funds_locked');
    expect($order->reversal_failed_at)->not->toBeNull();
    expect($order->reversal_failure_reason)->toContain('M-Pesa API unavailable');
});

test('payment expiry job does not affect seller-initiated orders', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $job = new ExpirePayment($order);
    $job->handle(app(OrderExpiryService::class), app(AuditService::class));

    $order->refresh();
    $this->assertEquals('pending_accept', $order->status);
});
