<?php

use App\Models\Order;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\SettingsService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->admin = User::factory()->create(['phone' => '700000001']);
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create(['phone' => '700000002']);
    $this->user->assignRole('buyer');
});

test('settings table has seeded defaults', function () {
    expect(Setting::where('key', 'flat_fee')->exists())->toBeTrue();
    expect(Setting::where('key', 'expiry_minutes')->exists())->toBeTrue();
    expect(Setting::where('key', 'payment_expiry_minutes')->exists())->toBeTrue();

    expect((int) Setting::where('key', 'flat_fee')->first()->value)->toBe(5000);
});

test('settings service reads from table', function () {
    $service = app(SettingsService::class);

    $fee = $service->get('flat_fee');

    expect($fee)->toBe(5000);
});

test('settings service falls back to config', function () {
    Setting::where('key', 'flat_fee')->delete();

    $service = app(SettingsService::class);

    $fee = $service->get('flat_fee', 9999);

    expect($fee)->toBe(5000);
});

test('settings service returns default when not found', function () {
    $service = app(SettingsService::class);

    $result = $service->get('non_existent_key', 'fallback_value');

    expect($result)->toBe('fallback_value');
});

test('admin can view all settings', function () {
    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/settings');

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['flat_fee', 'expiry_minutes', 'payment_expiry_minutes']]);
});

test('admin can update flat fee', function () {
    $response = $this
        ->actingAs($this->admin)
        ->putJson('/api/admin/settings/flat_fee', [
            'value' => 10000,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('settings', [
        'key' => 'flat_fee',
        'value' => '10000',
    ]);

    $service = app(SettingsService::class);
    expect($service->get('flat_fee'))->toBe(10000);
});

test('admin cannot update unknown setting key', function () {
    $response = $this
        ->actingAs($this->admin)
        ->putJson('/api/admin/settings/unknown_key', [
            'value' => 'test',
        ]);

    $response->assertStatus(422);
});

test('admin cannot manage settings without permission', function () {
    $response = $this
        ->actingAs($this->user)
        ->getJson('/api/admin/settings');

    $response->assertStatus(403);

    $response = $this
        ->actingAs($this->user)
        ->putJson('/api/admin/settings/flat_fee', [
            'value' => 10000,
        ]);

    $response->assertStatus(403);
});

test('order service uses settings for flat fee', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings/flat_fee', [
            'value' => 9999,
        ]);

    $seller = Seller::factory()->create(['user_id' => $this->admin->id]);

    $orderData = [
        'seller_id' => $seller->id,
        'buyer_phone' => $this->user->phone,
        'item_description' => 'Test item',
        'price' => 100000,
        'delivery_type' => 'shop_delivery',
        'delivery_location' => 'Nairobi',
    ];

    $this->actingAs($this->admin)
        ->postJson('/api/orders/seller-initiated', $orderData)
        ->assertCreated();

    $order = Order::where('item_description', 'Test item')->first();
    expect($order)->not->toBeNull();
    expect($order->flat_fee)->toBe(9999);
});

test('order service uses settings for expiry', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings/expiry_minutes', [
            'value' => 15,
        ]);

    $seller = Seller::factory()->create(['user_id' => $this->admin->id]);

    $orderData = [
        'seller_id' => $seller->id,
        'buyer_phone' => $this->user->phone,
        'item_description' => 'Expiry test item',
        'price' => 50000,
        'delivery_type' => 'shop_delivery',
        'delivery_location' => 'Nairobi',
    ];

    $this->actingAs($this->admin)
        ->postJson('/api/orders/seller-initiated', $orderData)
        ->assertCreated();

    $order = Order::where('item_description', 'Expiry test item')->first();
    expect($order)->not->toBeNull();
    expect((int) $order->expiry_at->diffInMinutes(now()))->toBeGreaterThanOrEqual(14);
});

test('fee preview returns correct breakdown', function () {
    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/fee-preview/1000');

    $response->assertOk();

    $data = $response->json('data');

    expect($data['orderAmount'])->toBe(1000);
    expect($data['orderAmountCents'])->toBe(100000);
    expect($data['flatFeeAmount'])->toBe(5000);
    expect($data['sellerReceivable'])->toBe(95000);
    expect($data['displayText'])->toContain('You will receive');
});

test('fee preview validates amount', function () {
    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/fee-preview/0');

    $response->assertStatus(422);

    $response = $this
        ->actingAs($this->admin)
        ->getJson('/api/admin/fee-preview/-1');

    $response->assertStatus(422);
});

test('setting update logs audit', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings/flat_fee', [
            'value' => 7500,
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.settings.updated',
        'entity' => 'settings',
    ]);

    $log = \App\Models\AuditLog::where('action', 'admin.settings.updated')->first();
    expect($log->details)->toHaveKey('key', 'flat_fee');
    expect($log->details)->toHaveKey('old_value', 5000);
    expect($log->details)->toHaveKey('new_value', '7500');
});
