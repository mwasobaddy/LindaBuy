<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'sender_id' => User::factory(),
            'sender_type' => fake()->randomElement(['buyer', 'seller']),
            'message' => fake()->sentence(),
        ];
    }
}
