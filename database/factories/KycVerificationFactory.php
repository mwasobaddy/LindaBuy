<?php

namespace Database\Factories;

use App\Models\KycVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycVerification>
 */
class KycVerificationFactory extends Factory
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
            'id_number' => fake()->numberBetween(10000000, 99999999),
            'kyc_photo_path' => 'kyc-photos/' . fake()->uuid() . '.jpg',
            'id_copy_path' => 'id-copies/' . fake()->uuid() . '.jpg',
            'kyc_status' => 'PENDING',
            'submitted_at' => now(),
        ];
    }
}
