<?php

use App\Jobs\ReleaseG4sOrder;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrderService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->adminUser = User::factory()->create(['phone' => '700000001', 'role' => 'buyer']);
    $this->adminUser->assignRole('admin');

    $this->sellerUser = User::factory()->create(['phone' => '700000002', 'role' => 'seller:approved']);
    $this->sellerUser->assignRole('seller');
    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'APPROVED',
    ]);

    $this->buyerUser = User::factory()->create(['phone' => '700000003', 'role' => 'buyer']);
    $this->buyerUser->assignRole('buyer');

    $this->agentUser = User::factory()->create(['phone' => '700000004', 'role' => 'agent:approved']);
    $this->agentUser->assignRole('agent');
    $this->agent = Agent::factory()->create([
        'user_id' => $this->agentUser->id,
        'kyc_status' => 'APPROVED',
    ]);
});

function makeG4sOrder(string $status, User $buyerUser, Seller $seller, Agent $agent): Order
{
    return Order::factory()->create([
        'buyer_id' => $buyerUser->id,
        'seller_id' => $seller->id,
        'agent_id' => $agent->id,
        'status' => $status,
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'g4s',
        'g4s_branch' => 'Nairobi Central',
        'g4s_tracking_ref' => 'G4S-REF-001',
        'expiry_at' => now()->addMinutes(5),
    ]);
}

test('admin can confirm G4S pickup', function () {
    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$order->id}/confirm-g4s-pickup");

    $response->assertOk();
    $order->refresh();

    expect($order->status)->toBe('g4s_pickup_confirmed');
    expect($order->g4s_pickup_confirmed_at)->not->toBeNull();
});

test('admin cannot confirm pickup on non-G4S order', function () {
    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'agent_id' => $this->agent->id,
        'status' => 'verified',
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$order->id}/confirm-g4s-pickup");

    $response->assertStatus(422);
});

test('admin cannot confirm pickup on wrong status', function () {
    $order = makeG4sOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$order->id}/confirm-g4s-pickup");

    $response->assertStatus(422);
});

test('non-admin cannot confirm pickup', function () {
    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->buyerUser)
        ->patchJson("/api/admin/orders/{$order->id}/confirm-g4s-pickup");

    $response->assertStatus(403);
});

test('admin can set auto-release timer', function () {
    Queue::fake();

    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$order->id}/auto-release", [
            'release_hours' => 24,
        ]);

    $response->assertOk();
    $order->refresh();

    expect($order->auto_release_enabled)->toBeTrue();
    expect($order->auto_release_at)->not->toBeNull();

    Queue::assertPushed(ReleaseG4sOrder::class);
});

test('admin can view pending G4S orders', function () {
    $order1 = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);
    $order2 = makeG4sOrder('g4s_pickup_confirmed', $this->buyerUser, $this->seller, $this->agent);
    // Non-G4S order should not appear
    Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'in_transit',
        'initiator_type' => 'buyer',
        'price' => 30000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/g4s-pending-release');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

test('admin can view G4S order details', function () {
    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->getJson("/api/admin/orders/{$order->id}/g4s-details");

    $response->assertOk();
    $response->assertJsonPath('data.g4s_branch', 'Nairobi Central');
    $response->assertJsonPath('data.g4s_tracking_ref', 'G4S-REF-001');
});

test('auto-release job dispatches and releases payment after scheduled time', function () {
    Queue::fake();

    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$order->id}/auto-release", [
            'release_hours' => 2,
        ]);

    $response->assertOk();
    $order->refresh();

    expect($order->auto_release_enabled)->toBeTrue();
    expect($order->auto_release_at)->not->toBeNull();

    Queue::assertPushed(ReleaseG4sOrder::class);
});

test('auto-release is idempotent', function () {
    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $order->update([
        'auto_release_enabled' => true,
        'auto_release_at' => now()->subMinute(),
    ]);

    $job1 = new ReleaseG4sOrder($order->fresh());
    $job1->handle(app(OrderService::class), app(AuditService::class));

    $order->refresh();
    expect($order->status)->toBe('released');
    expect($order->auto_release_enabled)->toBeFalse();

    // Second execution should do nothing (guards prevent double-fire)
    $job2 = new ReleaseG4sOrder($order);
    $job2->handle(app(OrderService::class), app(AuditService::class));

    $order->refresh();
    expect($order->status)->toBe('released');
});

test('non-admin cannot view pending G4S orders', function () {
    makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->buyerUser)
        ->getJson('/api/admin/g4s-pending-release');

    $response->assertStatus(403);
});

test('non-admin cannot view G4S details', function () {
    $order = makeG4sOrder('verified', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->buyerUser)
        ->getJson("/api/admin/orders/{$order->id}/g4s-details");

    $response->assertStatus(403);
});
