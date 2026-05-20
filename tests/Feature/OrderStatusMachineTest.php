<?php

use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Services\MpesaService;
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

    $this->thirdUser = User::factory()->create(['phone' => '700000003', 'role' => 'buyer']);
    $this->thirdUser->assignRole('buyer');
});

test('invalid transitions are rejected', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'released',
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
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertStatus(422);
});

test('seller-initiated pending_accept to accepted to funds_locked works', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_PAY',
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
    $this->assertEquals('funds_locked', $response->json('data.status'));
});

test('buyer-initiated pending_accept to funds_locked works', function () {
    $this->mock(MpesaService::class, function (MockInterface $mock) {
        $mock->shouldReceive('stkPush')
            ->once()
            ->andReturn([
                'CheckoutRequestID' => 'ws_CO_BUY',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
            ]);
    });

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson('/api/orders/buyer-initiated', [
            'seller_id' => $this->seller->id,
            'item_description' => 'Test item',
            'price' => 30000,
            'delivery_type' => 'shop_delivery',
        ]);

    $response->assertCreated();
    $this->assertEquals('funds_locked', $response->json('data.status'));
});

test('in_transit to delivered to released full shop delivery flow', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'in_transit',
        'initiator_type' => 'seller',
        'price' => 50000,
        'flat_fee' => 5000,
        'carrier_name' => 'Driver Name',
        'carrier_phone' => '712345678',
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/confirm-delivery");

    $response->assertOk();
    $this->assertEquals('delivered', $response->json('data.status'));

    $order->refresh();

    $token = $response->json('data.release_confirmation_token');

    $tokenResponse = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/request-release-confirmation");

    $tokenResponse->assertOk();

    $order->refresh();

    $this->assertNotNull($order->release_confirmation_token);

    $releaseResponse = $this
        ->actingAs($this->buyerUser)
        ->postJson("/api/orders/{$order->id}/release", [
            'confirmation_token' => $order->release_confirmation_token,
        ]);

    $releaseResponse->assertOk();
    $this->assertEquals('released', $releaseResponse->json('data.status'));
});

test('non-participant users cannot act on orders', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = $this
        ->actingAs($this->thirdUser)
        ->postJson("/api/orders/{$order->id}/accept");

    $response->assertStatus(422);
});

test('orders list shows user orders', function () {
    Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'pending_accept',
        'initiator_type' => 'seller',
        'price' => 50000,
    ]);

    $response = $this
        ->actingAs($this->buyerUser)
        ->getJson('/api/orders');

    $response->assertOk();
    $this->assertCount(1, $response->json('data'));
});
