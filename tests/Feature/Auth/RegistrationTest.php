<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'phone' => '712345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $user = User::where('phone', '712345678')->first();
    $this->assertNotNull($user);
    $response->assertRedirect(route('dashboard'));
});
