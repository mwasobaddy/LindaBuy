<?php

use App\Models\Agent;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->user = User::factory()->create([
        'phone' => '700000001',
        'email' => 'original@example.com',
    ]);
    $this->user->assignRole('buyer');
});

test('user_can_update_name', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => 'Updated Name',
        ])
        ->assertSuccessful()
        ->assertJson([
            'meta' => ['message' => 'Profile updated.'],
        ]);

    expect($this->user->fresh()->name)->toBe('Updated Name');
});

test('user_can_update_email', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'email' => 'newemail@example.com',
        ])
        ->assertSuccessful();

    expect($this->user->fresh()->email)->toBe('newemail@example.com');
    expect($this->user->fresh()->email_verified_at)->toBeNull();
});

test('user_cannot_update_email_to_duplicate', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'email' => 'taken@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user_can_upload_avatar', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('avatar.jpg');

    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'avatar' => $file,
        ])
        ->assertSuccessful();

    $user = $this->user->fresh();

    expect($user->profile_photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->profile_photo_path);
});

test('user_avatar_replaces_old_photo', function () {
    Storage::fake('public');

    $firstFile = UploadedFile::fake()->image('first.jpg');
    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'avatar' => $firstFile,
        ])
        ->assertSuccessful();

    $oldPath = $this->user->fresh()->profile_photo_path;
    Storage::disk('public')->assertExists($oldPath);

    $secondFile = UploadedFile::fake()->image('second.jpg');
    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'avatar' => $secondFile,
        ])
        ->assertSuccessful();

    $newPath = $this->user->fresh()->profile_photo_path;

    expect($oldPath)->not->toBe($newPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('user_cannot_upload_invalid_file_type', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'avatar' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['avatar']);
});

test('user_cannot_upload_oversized_file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('large.jpg')->size(3000);

    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'name' => $this->user->name,
            'avatar' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['avatar']);
});

test('phone_cannot_be_changed_via_profile', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/profile', [
            'phone' => '712345678',
            'name' => 'Some Name',
        ])
        ->assertSuccessful();

    expect($this->user->fresh()->phone)->toBe('700000001');
});

test('avatar_is_included_in_user_response', function () {
    $userData = $this->user->toArray();
    expect(array_key_exists('avatar', $userData))->toBeTrue();
    expect($userData['avatar'])->toBeNull();
});

test('guest_cannot_update_profile', function () {
    $this->patchJson('/api/profile', [
        'name' => 'Hacker',
    ])->assertStatus(401);
});

test('agent_can_update_name_via_agent_endpoint', function () {
    $agent = Agent::factory()->create([
        'user_id' => $this->user->id,
        'kyc_status' => 'APPROVED',
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/agents/{$agent->id}/update-profile", [
            'name' => 'Agent Updated Name',
        ])
        ->assertSuccessful();

    expect($this->user->fresh()->name)->toBe('Agent Updated Name');
});

test('agent_cannot_update_another_agents_profile', function () {
    $otherUser = User::factory()->create(['phone' => '712345678']);
    $otherAgent = Agent::factory()->create([
        'user_id' => $otherUser->id,
        'kyc_status' => 'APPROVED',
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/agents/{$otherAgent->id}/update-profile", [
            'name' => 'Should Not Work',
        ])
        ->assertForbidden();
});

test('unauthenticated_cannot_access_settings_page', function () {
    $this->get('/settings/profile')->assertRedirect('/login');
});
