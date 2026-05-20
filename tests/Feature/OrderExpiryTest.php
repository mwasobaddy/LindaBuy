<?php

use App\Jobs\ExpireOrder;
use App\Jobs\ExpirePayment;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
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
    $job->handle(app(OrderExpiryService::class));

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
    $job->handle(app(OrderExpiryService::class));

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
    $job->handle(app(OrderExpiryService::class));

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
    $job->handle(app(OrderExpiryService::class));

    $order->refresh();
    $this->assertEquals('pending_accept', $order->status);
});
