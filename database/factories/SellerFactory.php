<?php

namespace Database\Factories;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'shop_name' => fake()->company(),
            'shop_location' => fake()->address(),
            'shop_location_coords_lat' => fake()->latitude(),
            'shop_location_coords_lng' => fake()->longitude(),
            'verification_status' => 'PENDING',
        ];
    }
}
