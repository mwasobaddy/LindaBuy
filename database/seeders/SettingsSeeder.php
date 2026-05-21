<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'flat_fee',
                'value' => '5000',
                'type' => 'integer',
                'description' => 'Flat fee deducted from seller payout (in cents)',
            ],
            [
                'key' => 'expiry_minutes',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Order expiry timeout in minutes',
            ],
            [
                'key' => 'payment_expiry_minutes',
                'value' => '2',
                'type' => 'integer',
                'description' => 'Payment window timeout in minutes',
            ],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
