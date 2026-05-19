<?php

use App\Models\Agent;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function makeAdminAgent(): User
{
    $admin = User::factory()->create(['role' => 'buyer']);
    $admin->assignRole('admin');

    return $admin;
}

function makePendingAgent(): Agent
{
    $user = User::factory()->create(['role' => 'agent:pending']);
    $user->assignRole('buyer');

    return Agent::create([
        'user_id' => $user->id,
        'kyc_status' => 'PENDING',
    ]);
}

test('admin can approve agent', function () {
    $admin = makeAdminAgent();
    $agent = makePendingAgent();

    $response = $this
        ->actingAs($admin)
        ->patchJson("/api/admin/agents/{$agent->id}/approve");

    $response->assertOk();

    $agent->refresh();
    expect($agent->kyc_status)->toBe('APPROVED');

    $agent->user->refresh();
    expect($agent->user->role)->toBe('agent:approved');
    expect($agent->user->hasRole('agent'))->toBeTrue();
});

test('admin can reject agent with reason', function () {
    $admin = makeAdminAgent();
    $agent = makePendingAgent();

    $response = $this
        ->actingAs($admin)
        ->patchJson("/api/admin/agents/{$agent->id}/reject", [
            'rejection_reason' => 'Incomplete documents',
        ]);

    $response->assertOk();

    $agent->refresh();
    expect($agent->kyc_status)->toBe('REJECTED');
    expect($agent->rejected_reason)->toBe('Incomplete documents');
    expect($agent->user->role)->toBe('agent:pending');
});

test('reject agent fails without reason', function () {
    $admin = makeAdminAgent();
    $agent = makePendingAgent();

    $response = $this
        ->actingAs($admin)
        ->patchJson("/api/admin/agents/{$agent->id}/reject", []);

    $response->assertStatus(422);
});

test('non admin cannot access agent admin endpoints', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');
    $agent = makePendingAgent();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/admin/agents/pending');

    $response->assertStatus(403);
});
