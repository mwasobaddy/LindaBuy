<?php

use App\Models\KycVerification;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function makeAdminSeller(): User
{
    $admin = User::factory()->create(['role' => 'buyer']);
    $admin->assignRole('admin');

    return $admin;
}

function makePendingSeller(): Seller
{
    $user = User::factory()->create(['role' => 'seller:pending']);
    $user->assignRole('buyer');

    KycVerification::create([
        'user_id' => $user->id,
        'id_number' => '12345678',
        'kyc_photo_path' => 'test.jpg',
        'id_copy_path' => 'test2.jpg',
        'kyc_status' => 'PENDING',
        'submitted_at' => now(),
    ]);

    return Seller::create([
        'user_id' => $user->id,
        'shop_name' => 'Test Shop',
        'verification_status' => 'PENDING',
    ]);
}

test('admin can approve kyc', function () {
    $admin = makeAdminSeller();
    $seller = makePendingSeller();

    $response = $this
        ->actingAs($admin)
        ->patchJson("/api/admin/sellers/{$seller->id}/approve-kyc");

    $response->assertOk();

    $seller->user->kycVerification->refresh();
    expect($seller->user->kycVerification->kyc_status)->toBe('APPROVED');
});

test('admin can reject kyc with reason', function () {
    $admin = makeAdminSeller();
    $seller = makePendingSeller();

    $response = $this
        ->actingAs($admin)
        ->patchJson("/api/admin/sellers/{$seller->id}/reject-kyc", [
            'rejection_reason' => 'Blurry photo',
        ]);

    $response->assertOk();

    $seller->user->kycVerification->refresh();
    expect($seller->user->kycVerification->kyc_status)->toBe('REJECTED');
    expect($seller->user->kycVerification->rejected_reason)->toBe('Blurry photo');
});

test('reject kyc fails without reason', function () {
    $admin = makeAdminSeller();
    $seller = makePendingSeller();

    $response = $this
        ->actingAs($admin)
        ->patchJson("/api/admin/sellers/{$seller->id}/reject-kyc", []);

    $response->assertStatus(422);
});

test('both kyc and shop approved grants seller role', function () {
    $admin = makeAdminSeller();
    $seller = makePendingSeller();

    // Approve KYC
    $this->actingAs($admin)->patchJson("/api/admin/sellers/{$seller->id}/approve-kyc");
    // User should still be seller:pending
    $seller->user->refresh();
    expect($seller->user->role)->toBe('seller:pending');

    // Approve shop
    $this->actingAs($admin)->patchJson("/api/admin/sellers/{$seller->id}/approve-shop");

    $seller->user->refresh();
    expect($seller->user->role)->toBe('seller:approved');
    expect($seller->user->hasRole('seller'))->toBeTrue();
});

test('only kyc approved keeps user as seller pending', function () {
    $admin = makeAdminSeller();
    $seller = makePendingSeller();

    $this->actingAs($admin)->patchJson("/api/admin/sellers/{$seller->id}/approve-kyc");

    $seller->user->refresh();
    expect($seller->user->role)->toBe('seller:pending');
    expect($seller->user->hasRole('seller'))->toBeFalse();
});

test('non admin cannot access admin endpoints', function () {
    $user = User::factory()->create(['role' => 'buyer']);
    $user->assignRole('buyer');
    $seller = makePendingSeller();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/admin/sellers/pending');

    $response->assertStatus(403);
});
