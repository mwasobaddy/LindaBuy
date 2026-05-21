<?php

use App\Jobs\RetryReversal;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Services\MpesaService;
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

function makeFailedReversalOrder(string $status, User $buyerUser, Seller $seller, Agent $agent): Order
{
    return Order::factory()->create([
        'buyer_id' => $buyerUser->id,
        'seller_id' => $seller->id,
        'agent_id' => $agent->id,
        'status' => $status,
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
        'expiry_at' => now()->addMinutes(5),
        'reversal_failed_at' => now()->subHour(),
        'reversal_failure_reason' => 'M-Pesa API timeout',
        'reversal_attempts' => 1,
        'mpesa_transaction_id' => 'NFC4JN6Q1G',
    ]);
}

test('admin can view failed reversals', function () {
    makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/failed-reversals');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('admin cannot view failed reversals without permission', function () {
    makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->buyerUser)
        ->getJson('/api/admin/failed-reversals');

    $response->assertStatus(403);
});

test('admin can retry reversal', function () {
    Queue::fake();

    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/retry-reversal");

    $response->assertOk();
    $response->assertJsonPath('meta.message', 'Reversal retry dispatched.');

    Queue::assertPushed(RetryReversal::class);
});

test('admin can retry reversal with mpesa transaction id', function () {
    Queue::fake();

    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);
    $order->update(['mpesa_transaction_id' => null]);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/retry-reversal", [
            'mpesa_transaction_id' => 'NFC4JN6Q1G',
        ]);

    $response->assertOk();
    $order->refresh();
    expect($order->mpesa_transaction_id)->toBe('NFC4JN6Q1G');

    Queue::assertPushed(RetryReversal::class);
});

test('admin cannot retry without mpesa transaction id', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);
    $order->update(['mpesa_transaction_id' => null]);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/retry-reversal");

    $response->assertStatus(422);
});

test('admin cannot retry resolved reversal', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);
    $order->update([
        'reversal_resolved_at' => now(),
        'reversal_resolution_type' => 'write_off',
    ]);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/retry-reversal");

    $response->assertStatus(422);
});

test('admin can force release', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/resolve-reversal", [
            'resolution_type' => 'force_release',
        ]);

    $response->assertOk();
    $order->refresh();

    expect($order->status)->toBe('released');
    expect($order->reversal_resolved_at)->not->toBeNull();
    expect($order->reversal_resolution_type)->toBe('force_release');
});

test('admin cannot force release on non funds_locked order', function () {
    $order = makeFailedReversalOrder('in_transit', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/resolve-reversal", [
            'resolution_type' => 'force_release',
        ]);

    $response->assertStatus(422);
});

test('admin can write off', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/resolve-reversal", [
            'resolution_type' => 'write_off',
        ]);

    $response->assertOk();
    $order->refresh();

    expect($order->status)->toBe('cancelled');
    expect($order->reversal_resolved_at)->not->toBeNull();
    expect($order->reversal_resolution_type)->toBe('write_off');
});

test('retry reversal job executes api call', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $mpesaService = Mockery::mock(MpesaService::class);
    $mpesaService->shouldReceive('reversal')
        ->once()
        ->with('NFC4JN6Q1G', 50000, Mockery::any())
        ->andReturn(['ResponseCode' => '0']);

    $ledgerService = app(LedgerService::class);
    $auditService = app(AuditService::class);

    $job = new RetryReversal($order);
    $job->handle($mpesaService, $ledgerService, $auditService);

    $order->refresh();
    expect($order->status)->toBe('cancelled');
    expect($order->reversal_failed_at)->toBeNull();
    expect($order->reversal_attempts)->toBe(2);
});

test('retry reversal job handles api failure', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);

    $mpesaService = Mockery::mock(MpesaService::class);
    $mpesaService->shouldReceive('reversal')
        ->once()
        ->andReturn(['ResponseCode' => '1', 'ResponseDescription' => 'Agent not found']);

    $ledgerService = app(LedgerService::class);
    $auditService = app(AuditService::class);

    $job = new RetryReversal($order);
    $job->handle($mpesaService, $ledgerService, $auditService);

    $order->refresh();
    expect($order->status)->toBe('funds_locked');
    expect($order->reversal_attempts)->toBe(2);
    expect($order->reversal_failure_reason)->toBe('Agent not found');
    expect($order->reversal_retry_at)->not->toBeNull();
});

test('retry reversal job is idempotent when no failed reversal', function () {
    $order = makeFailedReversalOrder('funds_locked', $this->buyerUser, $this->seller, $this->agent);
    $order->update(['reversal_failed_at' => null]);

    $mpesaService = Mockery::mock(MpesaService::class);
    $mpesaService->shouldNotReceive('reversal');

    $ledgerService = app(LedgerService::class);
    $auditService = app(AuditService::class);

    $job = new RetryReversal($order);
    $job->handle($mpesaService, $ledgerService, $auditService);
});
