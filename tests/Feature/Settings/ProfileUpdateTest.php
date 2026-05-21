<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'phone' => $user->phone,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
});

test('phone_cannot_be_changed_via_web_profile', function () {
    $user = User::factory()->create(['mobile_verified_at' => now(), 'phone' => '700000001']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->phone)->toBe('700000001');
});

test('user can delete their account', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('api request can update profile and receive json response', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => 'API User',
            'phone' => $user->phone,
        ]);

    $response->assertOk();
    $response->assertJson([
        'data' => [],
        'meta' => ['message' => 'Profile updated.'],
    ]);

    expect($user->refresh()->name)->toBe('API User');
});

test('api request can delete account and receive json response', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response->assertOk();
    $response->assertJson([
        'data' => [],
        'meta' => ['message' => 'Account deleted.'],
    ]);

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});
