<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'seller_id' => Seller::factory(),
            'status' => 'pending_accept',
            'item_description' => fake()->sentence(),
            'price' => fake()->numberBetween(10000, 500000),
            'flat_fee' => 5000,
            'delivery_type' => 'shop_delivery',
            'delivery_location' => fake()->address(),
            'delivery_location_coords' => null,
            'expiry_at' => now()->addMinutes(5),
            'initiator_type' => 'seller',
        ];
    }
}
