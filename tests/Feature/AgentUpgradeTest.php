<?php

use App\Models\Agent;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RoleAndPermissionSeeder::class);
});

test('buyer can request agent upgrade', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $response = $this
        ->actingAs($user)
        ->postJson('/api/agents/request', [
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['agent_id'],
        'meta' => ['message'],
    ]);

    $this->assertDatabaseHas('agents', [
        'user_id' => $user->id,
        'kyc_status' => 'PENDING',
    ]);

    $this->assertDatabaseHas('kyc_verifications', [
        'user_id' => $user->id,
        'kyc_status' => 'PENDING',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'agent:pending',
    ]);
});

test('duplicate agent request rejected', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    Agent::create([
        'user_id' => $user->id,
        'kyc_status' => 'PENDING',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/api/agents/request', [
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $response->assertStatus(422);
});

test('user role changes to agent pending', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $this
        ->actingAs($user)
        ->postJson('/api/agents/request', [
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $user->refresh();
    expect($user->role)->toBe('agent:pending');
});
