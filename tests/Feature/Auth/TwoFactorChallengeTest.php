<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/two-factor-challenge'),
        );
});

test('api request with valid recovery code receives json response after two factor challenge', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('two-factor.login'), [
            'recovery_code' => 'recovery-code-1',
        ]);

    $response->assertOk();
    $response->assertExactJson(['data' => ['two_factor' => false], 'meta' => []]);
});
