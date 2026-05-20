<?php

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

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

    $this->order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
    ]);
});

// --- Viewing messages ---

test('buyer can view order messages', function () {
    ChatMessage::factory()->count(3)->create([
        'order_id' => $this->order->id,
        'sender_id' => $this->sellerUser->id,
        'sender_type' => 'seller',
    ]);

    $response = actingAs($this->buyerUser)
        ->getJson("/api/orders/{$this->order->id}/messages");

    $response->assertOk();
    $response->assertJsonCount(3, 'data.data');
});

test('seller can view order messages', function () {
    ChatMessage::factory()->count(2)->create([
        'order_id' => $this->order->id,
        'sender_id' => $this->buyerUser->id,
        'sender_type' => 'buyer',
    ]);

    $response = actingAs($this->sellerUser)
        ->getJson("/api/orders/{$this->order->id}/messages");

    $response->assertOk();
    $response->assertJsonCount(2, 'data.data');
});

test('non-participant cannot view messages', function () {
    $stranger = User::factory()->create(['phone' => '700000099']);
    $stranger->assignRole('buyer');

    $response = actingAs($stranger)
        ->getJson("/api/orders/{$this->order->id}/messages");

    $response->assertForbidden();
});

test('agent cannot view messages', function () {
    $agentUser = User::factory()->create(['phone' => '700000003', 'role' => 'agent:approved']);
    $agentUser->assignRole('agent');

    $response = actingAs($agentUser)
        ->getJson("/api/orders/{$this->order->id}/messages");

    $response->assertForbidden();
});

test('admin cannot view messages', function () {
    $adminUser = User::factory()->create(['phone' => '700000004']);
    $adminUser->assignRole('admin');

    $response = actingAs($adminUser)
        ->getJson("/api/orders/{$this->order->id}/messages");

    $response->assertForbidden();
});

// --- Sending messages ---

test('buyer can send a message', function () {
    Event::fake();

    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello seller!',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.message', 'Hello seller!');
    $response->assertJsonPath('data.sender_type', 'buyer');

    $this->assertDatabaseHas('chat_messages', [
        'order_id' => $this->order->id,
        'sender_id' => $this->buyerUser->id,
        'sender_type' => 'buyer',
        'message' => 'Hello seller!',
    ]);

    Event::assertDispatched(MessageSent::class);
});

test('seller can send a message', function () {
    Event::fake();

    $response = actingAs($this->sellerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello buyer!',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.message', 'Hello buyer!');
    $response->assertJsonPath('data.sender_type', 'seller');

    Event::assertDispatched(MessageSent::class);
});

test('non-participant cannot send a message', function () {
    $stranger = User::factory()->create(['phone' => '700000099']);
    $stranger->assignRole('buyer');

    $response = actingAs($stranger)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello!',
        ]);

    $response->assertForbidden();
});

test('agent cannot send a message', function () {
    $agentUser = User::factory()->create(['phone' => '700000003', 'role' => 'agent:approved']);
    $agentUser->assignRole('agent');

    $response = actingAs($agentUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello!',
        ]);

    $response->assertForbidden();
});

test('admin cannot send a message', function () {
    $adminUser = User::factory()->create(['phone' => '700000004']);
    $adminUser->assignRole('admin');

    $response = actingAs($adminUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello!',
        ]);

    $response->assertForbidden();
});

// --- Validation ---

test('empty message is rejected', function () {
    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => '',
        ]);

    $response->assertStatus(422);
});

test('message exceeding max length is rejected', function () {
    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => str_repeat('a', 1001),
        ]);

    $response->assertStatus(422);
});

// --- Order state restrictions ---

test('cannot send message on cancelled order', function () {
    $this->order->update(['status' => 'cancelled']);

    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello!',
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.0.message', 'Cannot send messages on a completed or cancelled order.');
});

test('cannot send message on released order', function () {
    $this->order->update(['status' => 'released']);

    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello!',
        ]);

    $response->assertStatus(422);
});

test('cannot send message on expired order', function () {
    $this->order->update(['status' => 'expired']);

    $response = actingAs($this->buyerUser)
        ->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'Hello!',
        ]);

    $response->assertStatus(422);
});

// --- Broadcasting ---

test('message broadcast event has correct payload', function () {
    $message = ChatMessage::factory()->create([
        'order_id' => $this->order->id,
        'sender_id' => $this->buyerUser->id,
        'sender_type' => 'buyer',
        'message' => 'Test broadcast payload',
    ]);

    $event = new MessageSent($message);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys(['id', 'order_id', 'sender_id', 'sender_type', 'message', 'created_at', 'sender_name']);
    expect($payload['id'])->toBe($message->id);
    expect($payload['message'])->toBe('Test broadcast payload');
    expect($payload['sender_name'])->toBe($this->buyerUser->name);
});

test('messages are paginated', function () {
    ChatMessage::factory()->count(60)->create([
        'order_id' => $this->order->id,
        'sender_id' => $this->sellerUser->id,
        'sender_type' => 'seller',
    ]);

    $response = actingAs($this->buyerUser)
        ->getJson("/api/orders/{$this->order->id}/messages?page=1");

    $response->assertOk();
    expect(count($response->json('data.data')))->toBeLessThanOrEqual(50);
    expect($response->json('data.total'))->toBe(60);
    expect($response->json('data.last_page'))->toBe(2);
});

test('message broadcast uses correct channel and name', function () {
    $message = ChatMessage::factory()->create([
        'order_id' => $this->order->id,
        'sender_id' => $this->buyerUser->id,
        'sender_type' => 'buyer',
        'message' => 'Channel test',
    ]);

    $event = new MessageSent($message);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-order.'.$this->order->id);
    expect($event->broadcastAs())->toBe('message.sent');
});
