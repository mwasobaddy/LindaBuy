<?php

use App\Models\Agent;
use App\Models\Order;
use App\Models\OrderIssueReport;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->sellerUser = User::factory()->create(['phone' => '700000001', 'role' => 'seller:approved']);
    $this->sellerUser->assignRole('seller');

    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'APPROVED',
    ]);

    $this->buyerUser = User::factory()->create(['phone' => '700000002', 'role' => 'buyer']);
    $this->buyerUser->assignRole('buyer');

    $this->agentUser = User::factory()->create(['phone' => '700000003', 'role' => 'agent:approved']);
    $this->agentUser->assignRole('agent');
    $this->agent = Agent::factory()->create([
        'user_id' => $this->agentUser->id,
        'kyc_status' => 'APPROVED',
    ]);

    $this->unapprovedAgentUser = User::factory()->create(['phone' => '700000004', 'role' => 'agent:pending']);
    $this->unapprovedAgentUser->assignRole('agent');
    $this->unapprovedAgent = Agent::factory()->create([
        'user_id' => $this->unapprovedAgentUser->id,
        'kyc_status' => 'PENDING',
    ]);

    $this->order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
        'expiry_at' => now()->addMinutes(5),
    ]);
});

test('approved agent can view available jobs', function () {
    $response = actingAs($this->agentUser)
        ->getJson('/api/orders/available-jobs');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('unapproved agent cannot view available jobs', function () {
    $response = actingAs($this->unapprovedAgentUser)
        ->getJson('/api/orders/available-jobs');

    $response->assertForbidden();
});

test('approved agent can accept a job', function () {
    Event::fake();

    $response = actingAs($this->agentUser)
        ->postJson("/api/orders/{$this->order->id}/accept-job");

    $response->assertOk();
    $this->assertEquals($this->agent->id, $this->order->fresh()->agent_id);
});

test('agent cannot accept already assigned job', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    $otherAgentUser = User::factory()->create(['phone' => '700000005', 'role' => 'agent:approved']);
    $otherAgentUser->assignRole('agent');
    $otherAgent = Agent::factory()->create([
        'user_id' => $otherAgentUser->id,
        'kyc_status' => 'APPROVED',
    ]);

    $response = actingAs($otherAgentUser)
        ->postJson("/api/orders/{$this->order->id}/accept-job");

    $response->assertStatus(422);
});

test('non-agent cannot accept a job', function () {
    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/accept-job");

    $response->assertForbidden();
});

test('agent can verify shop delivery order', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    $response = actingAs($this->agentUser)
        ->postJson("/api/orders/{$this->order->id}/verify", [
            'carrier_name' => 'John Driver',
            'carrier_phone' => '0712345678',
        ]);

    $response->assertOk();
    $this->assertEquals('in_transit', $this->order->fresh()->status);
});

test('agent can verify g4s order', function () {
    $g4sOrder = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'agent_id' => $this->agent->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 75000,
        'flat_fee' => 5000,
        'delivery_type' => 'g4s',
        'expiry_at' => now()->addMinutes(5),
    ]);

    $response = actingAs($this->agentUser)
        ->postJson("/api/orders/{$g4sOrder->id}/verify", [
            'g4s_branch' => 'Mombasa Main',
            'g4s_tracking_ref' => 'G4S-REF-001',
        ]);

    $response->assertOk();
    $this->assertEquals('verified', $g4sOrder->fresh()->status);
    $this->assertEquals('Mombasa Main', $g4sOrder->fresh()->g4s_branch);
});

test('agent can report an issue', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    $response = actingAs($this->agentUser)
        ->postJson("/api/orders/{$this->order->id}/report-issue", [
            'issue_type' => 'QUALITY_ISSUE',
            'description' => 'Item is damaged',
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('order_issue_reports', [
        'order_id' => $this->order->id,
        'agent_id' => $this->agent->id,
        'issue_type' => 'QUALITY_ISSUE',
        'status' => 'REPORTED',
    ]);
});

test('agent cannot verify with unresolved issue', function () {
    $this->order->update(['agent_id' => $this->agent->id]);

    OrderIssueReport::create([
        'order_id' => $this->order->id,
        'agent_id' => $this->agent->id,
        'issue_type' => 'QUALITY_ISSUE',
        'description' => 'Damaged item',
        'status' => 'REPORTED',
        'reported_at' => now(),
    ]);

    $response = actingAs($this->agentUser)
        ->postJson("/api/orders/{$this->order->id}/verify", [
            'carrier_name' => 'John Driver',
            'carrier_phone' => '0712345678',
        ]);

    $response->assertStatus(422);
});

test('agent cannot verify order not assigned to them', function () {
    $otherAgentUser = User::factory()->create(['phone' => '700000006', 'role' => 'agent:approved']);
    $otherAgentUser->assignRole('agent');
    $otherAgent = Agent::factory()->create([
        'user_id' => $otherAgentUser->id,
        'kyc_status' => 'APPROVED',
    ]);

    $this->order->update(['agent_id' => $otherAgent->id]);

    $response = actingAs($this->agentUser)
        ->postJson("/api/orders/{$this->order->id}/verify", [
            'carrier_name' => 'John Driver',
            'carrier_phone' => '0712345678',
        ]);

    $response->assertStatus(422);
});
