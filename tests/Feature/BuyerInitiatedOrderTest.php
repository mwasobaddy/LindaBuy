<?php

use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\OrderExpiryService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Mockery\MockInterface;

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

test('buyer can create a buyer-initiated order with stk push', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_BUYER_ORDER',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
            ]);
    });

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson('/api/orders/buyer-initiated', [
            'seller_id' => $this->seller->id,
            'item_description' => 'Item I want to buy',
            'price' => 30000,
            'delivery_type' => 'g4s',
            'delivery_location' => 'Mombasa',
        ]);

    $response->assertCreated();
    $this->assertEquals('funds_locked', $response->json('data.status'));
    $this->assertEquals('ws_CO_BUYER_ORDER', $response->json('data.checkout_request_id'));
});

test('seller can accept buyer-initiated order', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertOk();
    $this->assertEquals('funds_locked', $response->json('data.status'));
    $this->assertNotNull($response->json('data.seller_accepted_at'));
});

test('seller can decline buyer-initiated order with reversal', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson("/api/orders/{$order->id}/decline", [
            'reason' => 'Out of stock',
        ]);

    $response->assertOk();
    $this->assertEquals('cancelled', $response->json('data.status'));
});

test('order expires on funds_locked for buyer-initiated', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->subMinutes(5),
    ]);

    $expiryService = app(OrderExpiryService::class);
    $expiryService->handleOrderExpiry($order);

    $order->refresh();
    $this->assertEquals('cancelled', $order->status);
});

test('non-buyer user cannot create buyer-initiated order', function () {
    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson('/api/orders/buyer-initiated', [
            'seller_id' => $this->seller->id,
            'item_description' => 'Item',
            'price' => 30000,
            'delivery_type' => 'shop_delivery',
        ]);

    $response->assertStatus(422);
});

test('buyer cannot accept their own buyer-initiated order', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertStatus(422);
});
