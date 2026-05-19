<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

test('registration screen can be rendered', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'phone' => '712345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $user = User::where('phone', '712345678')->first();
    $this->assertNotNull($user);
    $response->assertRedirect('/auth/otp-verify');
});
