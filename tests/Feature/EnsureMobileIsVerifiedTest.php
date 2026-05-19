<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['mobile_verified_at' => null]);
});

test('guest is redirected to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('user without verified mobile is redirected to otp verify page', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertRedirect(route('auth.otp.verify.page'));
});

test('api request without verified mobile gets json error', function () {
    $response = $this->actingAs($this->user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('dashboard'));

    $response->assertForbidden();
    $response->assertJson([
        'success' => false,
        'message' => 'Mobile number not verified.',
    ]);
});

test('user with verified mobile can access dashboard', function () {
    $user = User::factory()->create(['mobile_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});
