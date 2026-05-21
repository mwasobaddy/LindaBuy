<?php

use App\Models\Agent;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->adminUser = User::factory()->create(['phone' => '700000001', 'role' => 'buyer']);
    $this->adminUser->assignRole('admin');

    $this->regularUser = User::factory()->create(['phone' => '700000002', 'role' => 'buyer']);
    $this->regularUser->assignRole('buyer');

    $sellerUser = User::factory()->create(['phone' => '700000003', 'role' => 'seller:approved']);
    $sellerUser->assignRole('seller');
    $this->seller = Seller::factory()->create(['user_id' => $sellerUser->id]);

    $buyerUser = User::factory()->create(['phone' => '700000004', 'role' => 'buyer']);
    $buyerUser->assignRole('buyer');

    $this->agentUser = User::factory()->create(['phone' => '700000005', 'role' => 'agent:approved']);
    $this->agentUser->assignRole('agent');
    $this->agent = Agent::factory()->create(['user_id' => $this->agentUser->id, 'kyc_status' => 'APPROVED']);

    $this->pendingAgentUser = User::factory()->create(['phone' => '700000006', 'role' => 'agent:pending']);
    $this->pendingAgentUser->assignRole('buyer');
    $this->pendingAgent = Agent::factory()->create([
        'user_id' => $this->pendingAgentUser->id,
        'kyc_status' => 'PENDING',
    ]);

    $this->order = Order::factory()->create([
        'seller_id' => $this->seller->id,
        'buyer_id' => $buyerUser->id,
        'status' => 'funds_locked',
        'delivery_type' => 'shop_delivery',
        'flat_fee' => 5000,
    ]);
});

test('admin can assign agent to order', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/assign-agent", [
            'agent_id' => $this->agent->id,
        ]);

    $response->assertOk();
    expect($response->json('data.agent.id'))->toBe($this->agent->id);
});

test('admin cannot assign nonexistent agent', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/assign-agent", [
            'agent_id' => 99999,
        ]);

    $response->assertStatus(422);
});

test('admin cannot assign unapproved agent', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/assign-agent", [
            'agent_id' => $this->pendingAgent->id,
        ]);

    $response->assertStatus(422);
});

test('admin cannot assign to order with existing agent', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    $otherAgentUser = User::factory()->create(['phone' => '700000007', 'role' => 'agent:approved']);
    $otherAgentUser->assignRole('agent');
    $otherAgent = Agent::factory()->create(['user_id' => $otherAgentUser->id, 'kyc_status' => 'APPROVED']);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/assign-agent", [
            'agent_id' => $otherAgent->id,
        ]);

    $response->assertStatus(422);
});

test('admin can remove agent from order', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/remove-agent");

    $response->assertOk();
    expect($response->json('data.agent'))->toBeNull();
});

test('admin cannot remove agent from order without agent', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/remove-agent");

    $response->assertStatus(422);
});

test('admin cannot remove agent from in_transit order', function () {
    $this->order->update(['agent_id' => $this->agent->id, 'status' => 'in_transit']);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/remove-agent");

    $response->assertStatus(422);
});

test('non_admin cannot assign or remove agents', function () {
    actingAs($this->regularUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/assign-agent", [
            'agent_id' => $this->agent->id,
        ])
        ->assertForbidden();

    actingAs($this->regularUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/remove-agent")
        ->assertForbidden();
});

test('assign logs audit entry', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/assign-agent", [
            'agent_id' => $this->agent->id,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.order.agent_assigned',
        'entity' => 'order',
        'entity_id' => $this->order->id,
    ]);
});

test('remove logs audit entry', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/orders/{$this->order->id}/remove-agent");

    $response->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.order.agent_removed',
        'entity' => 'order',
        'entity_id' => $this->order->id,
    ]);
});
