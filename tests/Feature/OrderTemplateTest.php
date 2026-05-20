<?php

use App\Models\OrderTemplate;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->sellerUser = User::factory()->create(['phone' => '700000001', 'role' => 'seller:approved']);
    $this->sellerUser->assignRole('seller');

    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'APPROVED',
    ]);

    $this->buyerUser = User::factory()->create(['phone' => '700000002', 'role' => 'buyer']);
    $this->buyerUser->assignRole('buyer');
});

test('seller can create a template', function () {
    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson('/api/order-templates', [
            'template_name' => 'Standard Order',
            'item_description' => 'My standard product',
            'price' => 100000,
            'delivery_type' => 'shop_delivery',
            'delivery_location' => 'Nairobi',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.template_name', 'Standard Order');

    $this->assertDatabaseHas('order_templates', [
        'seller_id' => $this->seller->id,
        'template_name' => 'Standard Order',
    ]);
});

test('seller can list their templates', function () {
    OrderTemplate::factory()->create([
        'seller_id' => $this->seller->id,
        'template_name' => 'Template A',
    ]);

    OrderTemplate::factory()->create([
        'seller_id' => $this->seller->id,
        'template_name' => 'Template B',
    ]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->getJson('/api/order-templates');

    $response->assertOk();
    $this->assertCount(2, $response->json('data'));
});

test('seller can view a single template', function () {
    $template = OrderTemplate::factory()->create([
        'seller_id' => $this->seller->id,
        'template_name' => 'My Template',
    ]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->getJson("/api/order-templates/{$template->id}");

    $response->assertOk();
    $this->assertEquals('My Template', $response->json('data.template_name'));
});

test('seller can update a template', function () {
    $template = OrderTemplate::factory()->create([
        'seller_id' => $this->seller->id,
        'template_name' => 'Old Name',
    ]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->patchJson("/api/order-templates/{$template->id}", [
            'template_name' => 'New Name',
        ]);

    $response->assertOk();
    $this->assertEquals('New Name', $response->json('data.template_name'));
});

test('seller can delete a template', function () {
    $template = OrderTemplate::factory()->create([
        'seller_id' => $this->seller->id,
    ]);

    $response = $this
        ->actingAs($this->sellerUser)
        ->deleteJson("/api/order-templates/{$template->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('order_templates', ['id' => $template->id]);
});

test('non-seller cannot create templates', function () {
    $response = $this
        ->actingAs($this->buyerUser)
        ->postJson('/api/order-templates', [
            'template_name' => 'Test',
            'item_description' => 'Test desc',
            'price' => 10000,
            'delivery_type' => 'shop_delivery',
        ]);

    $response->assertStatus(403);
});

test('template fields are populated correctly', function () {
    $response = $this
        ->actingAs($this->sellerUser)
        ->postJson('/api/order-templates', [
            'template_name' => 'Complete Template',
            'item_description' => 'High quality product',
            'price' => 250000,
            'delivery_type' => 'g4s',
            'delivery_location' => 'Mombasa CBD',
        ]);

    $response->assertCreated();
    $this->assertEquals('Complete Template', $response->json('data.template_name'));
    $this->assertEquals('High quality product', $response->json('data.item_description'));
    $this->assertEquals(250000, $response->json('data.price'));
    $this->assertEquals('g4s', $response->json('data.delivery_type'));
    $this->assertEquals('Mombasa CBD', $response->json('data.delivery_location'));
});
