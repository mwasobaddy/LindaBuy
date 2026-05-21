<?php

use App\Models\Agent;
use App\Models\Order;
use App\Models\OrderIssueReport;
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
    $seller = Seller::factory()->create(['user_id' => $sellerUser->id]);

    $buyerUser = User::factory()->create(['phone' => '700000004', 'role' => 'buyer']);
    $buyerUser->assignRole('buyer');

    $agentUser = User::factory()->create(['phone' => '700000005', 'role' => 'agent:approved']);
    $agentUser->assignRole('agent');
    $agent = Agent::factory()->create(['user_id' => $agentUser->id, 'kyc_status' => 'APPROVED']);

    $this->order = Order::factory()->create([
        'seller_id' => $seller->id,
        'buyer_id' => $buyerUser->id,
        'status' => 'verified',
        'delivery_type' => 'shop_delivery',
        'flat_fee' => 5000,
    ]);

    $this->issueReport = OrderIssueReport::create([
        'order_id' => $this->order->id,
        'agent_id' => $agent->id,
        'issue_type' => 'QUALITY_ISSUE',
        'description' => 'Item is damaged',
        'status' => 'REPORTED',
        'reported_at' => now(),
    ]);
});

test('admin can view issue reports', function () {
    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/issue-reports');

    $response->assertOk();
    $response->assertJsonPath('data.data.0.id', $this->issueReport->id);
    expect($response->json('data.data'))->toHaveLength(1);
});

test('admin can filter issues by status', function () {
    OrderIssueReport::create([
        'order_id' => $this->order->id,
        'agent_id' => $this->issueReport->agent_id,
        'issue_type' => 'QUANTITY_MISMATCH',
        'description' => 'Wrong count',
        'status' => 'RESOLVED',
        'reported_at' => now(),
        'resolved_at' => now(),
    ]);

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/issue-reports?status=REPORTED');

    $response->assertOk();
    expect($response->json('data.data'))->toHaveLength(1);
    expect($response->json('data.data.0.status'))->toBe('REPORTED');
});

test('admin can resolve issue report', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/resolve");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'RESOLVED');
    expect($response->json('data.resolved_at'))->not->toBeNull();
});

test('admin cannot resolve already resolved issue', function () {
    $this->issueReport->update(['status' => 'RESOLVED', 'resolved_at' => now()]);

    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/resolve");

    $response->assertStatus(422);
});

test('admin can dismiss issue report', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/dismiss");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'DISMISSED');
});

test('non_admin cannot manage issues', function () {
    actingAs($this->regularUser)
        ->getJson('/api/admin/issue-reports')
        ->assertForbidden();

    actingAs($this->regularUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/resolve")
        ->assertForbidden();

    actingAs($this->regularUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/dismiss")
        ->assertForbidden();
});

test('resolve logs audit entry', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/resolve");

    $response->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.issue.resolved',
        'entity' => 'issue_report',
        'entity_id' => $this->issueReport->id,
    ]);
});

test('dismiss logs audit entry', function () {
    $response = actingAs($this->adminUser)
        ->patchJson("/api/admin/issue-reports/{$this->issueReport->id}/dismiss");

    $response->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.issue.dismissed',
        'entity' => 'issue_report',
        'entity_id' => $this->issueReport->id,
    ]);
});

test('guest cannot access issue reports', function () {
    $this->getJson('/api/admin/issue-reports')
        ->assertUnauthorized();
});
