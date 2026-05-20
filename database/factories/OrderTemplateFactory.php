<?php

namespace Database\Factories;

use App\Models\OrderTemplate;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTemplate>
 */
class OrderTemplateFactory extends Factory
{
    protected $model = OrderTemplate::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'template_name' => fake()->word().' Template',
            'item_description' => fake()->sentence(),
            'price' => fake()->numberBetween(10000, 200000),
            'delivery_type' => fake()->randomElement(['shop_delivery', 'g4s']),
            'delivery_location' => fake()->address(),
        ];
    }
}
