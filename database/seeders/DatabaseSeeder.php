<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleAndPermissionSeeder::class);

        // Seed chart of accounts
        $this->call(AccountSeeder::class);

        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '254700000001',
            'role' => 'buyer',
        ]);

        // Assign buyer role to the test user
        $user->assignRole('buyer');
    }
}
