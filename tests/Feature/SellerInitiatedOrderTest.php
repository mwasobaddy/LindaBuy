<?php

use App\Jobs\ExpireOrder;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\OrderExpiryService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Bus;
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

test('seller can create a seller-initiated order', function () {
    Bus::fake([ExpireOrder::class]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->zeroOrMoreTimes()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_ORDER_TEST',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
            ]);
    });

    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson('/api/orders/seller-initiated', [
            'seller_id' => $this->seller->id,
            'buyer_phone' => '700000002',
            'item_description' => 'Test item for sale',
            'price' => 50000,
            'delivery_type' => 'shop_delivery',
            'delivery_location' => 'Nairobi, Kenya',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'pending_accept');
    $response->assertJsonPath('data.initiator_type', 'seller');
    $response->assertJsonPath('data.buyer_id', $this->buyerUser->id);
    $response->assertJsonPath('data.seller_id', $this->seller->id);

    $this->assertDatabaseHas('orders', [
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
    ]);
});

test('flat fee is copied at order creation', function () {
    Bus::fake([ExpireOrder::class]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson('/api/orders/seller-initiated', [
            'seller_id' => $this->seller->id,
            'buyer_phone' => '700000002',
            'item_description' => 'Test item',
            'price' => 50000,
            'delivery_type' => 'shop_delivery',
        ]);

    $response->assertCreated();

    $flatFee = (int) config('orders.flat_fee', 5000);
    $this->assertEquals($flatFee, $response->json('data.flat_fee'));
});

test('buyer can accept seller-initiated order triggering stk push', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_ACCEPT',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
            ]);
    });

    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertOk();
    $this->assertContains($response->json('data.status'), ['accepted', 'funds_locked']);
});

test('buyer can decline seller-initiated order', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/decline", [
            'reason' => 'Not interested',
        ]);

    $response->assertOk();
    $this->assertEquals('cancelled', $response->json('data.status'));
});

test('non-seller cannot create seller-initiated order', function () {
    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson('/api/orders/seller-initiated', [
            'seller_id' => $this->seller->id,
            'buyer_phone' => '700000002',
            'item_description' => 'Test item',
            'price' => 50000,
            'delivery_type' => 'shop_delivery',
        ]);

    $response->assertStatus(422);
});

test('order expires if buyer does not act', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->subMinutes(5),
    ]);

    $expiryService = app(OrderExpiryService::class);
    $expiryService->handleOrderExpiry($order);

    $order->refresh();
    $this->assertEquals('expired', $order->status);
});

test('seller cannot accept their own seller-initiated order', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->zeroOrMoreTimes()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_TEST',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
            ]);
    });

    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertStatus(422);
});
