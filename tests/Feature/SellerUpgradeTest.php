<?php

use App\Models\KycVerification;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RoleAndPermissionSeeder::class);
});

test('buyer can request seller upgrade with valid data', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $response = $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'My Cool Shop',
            'shop_location' => 'Nairobi, Kenya',
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['seller_id'],
        'meta' => ['message'],
    ]);

    $this->assertDatabaseHas('sellers', [
        'user_id' => $user->id,
        'shop_name' => 'My Cool Shop',
        'verification_status' => 'PENDING',
    ]);

    $this->assertDatabaseHas('kyc_verifications', [
        'user_id' => $user->id,
        'id_number' => '12345678',
        'kyc_status' => 'PENDING',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'seller:pending',
    ]);
});

test('request fails without required fields', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $response = $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', []);

    $response->assertStatus(422);
});

test('request fails if user is not a buyer', function () {
    $user = User::factory()->create(['role' => 'seller:pending']);
    $user->assignRole('buyer');

    $response = $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'Shop',
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    // FormRequest authorize() returns false → 403
    $response->assertStatus(403);
});

test('kyc photos are stored correctly', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'Shop',
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    Storage::disk('public')->assertExists("kyc/{$user->id}/kyc_photo.jpg");
    Storage::disk('public')->assertExists("kyc/{$user->id}/id_copy.jpg");
});

test('user role changes to seller pending', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'Shop',
            'id_number' => '12345678',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $user->refresh();
    expect($user->role)->toBe('seller:pending');
});

test('duplicate requests rejected when pending kyc exists', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    // Create pending KYC
    KycVerification::create([
        'user_id' => $user->id,
        'id_number' => '12345678',
        'kyc_photo_path' => 'test.jpg',
        'id_copy_path' => 'test2.jpg',
        'kyc_status' => 'PENDING',
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'Another Shop',
            'id_number' => '87654321',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $response->assertStatus(422);
});

test('rejected user can resubmit after 24 hours', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    KycVerification::create([
        'user_id' => $user->id,
        'id_number' => '12345678',
        'kyc_photo_path' => 'test.jpg',
        'id_copy_path' => 'test2.jpg',
        'kyc_status' => 'REJECTED',
        'submitted_at' => now()->subHours(25),
        'rejected_at' => now()->subHours(25),
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'New Shop',
            'id_number' => '87654321',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $response->assertCreated();
});

test('rejected user cannot resubmit within 24 hours', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');

    KycVerification::create([
        'user_id' => $user->id,
        'id_number' => '12345678',
        'kyc_photo_path' => 'test.jpg',
        'id_copy_path' => 'test2.jpg',
        'kyc_status' => 'REJECTED',
        'submitted_at' => now()->subHours(2),
        'rejected_at' => now()->subHours(2),
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/api/sellers/request', [
            'shop_name' => 'New Shop',
            'id_number' => '87654321',
            'kyc_photo' => UploadedFile::fake()->image('kyc.jpg'),
            'id_copy' => UploadedFile::fake()->image('id_copy.jpg'),
        ]);

    $response->assertStatus(422);
});
