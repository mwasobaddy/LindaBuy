<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingsService
{
    protected const DESCRIPTION_MAP = [
        'flat_fee' => 'Flat fee deducted from seller payout (in cents)',
        'expiry_minutes' => 'Order expiry timeout in minutes',
        'payment_expiry_minutes' => 'Payment window timeout in minutes',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->first();

        if ($setting !== null) {
            return match ($setting->type) {
                'integer' => (int) $setting->value,
                'boolean' => (bool) $setting->value,
                default => $setting->value,
            };
        }

        $configValue = config("orders.{$key}");

        if ($configValue !== null) {
            return $configValue;
        }

        return $default;
    }

    public function set(string $key, mixed $value): Setting
    {
        $type = match (true) {
            is_int($value) => 'integer',
            is_bool($value) => 'boolean',
            default => 'string',
        };

        $description = self::DESCRIPTION_MAP[$key] ?? null;

        return Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'description' => $description,
            ]
        );
    }

    public function all(): Collection
    {
        return Setting::all()
            ->keyBy('key')
            ->map(function (Setting $setting): mixed {
                return match ($setting->type) {
                    'integer' => (int) $setting->value,
                    'boolean' => (bool) $setting->value,
                    default => $setting->value,
                };
            });
    }
}
