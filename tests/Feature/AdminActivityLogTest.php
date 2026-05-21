<?php

use App\Models\Account;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Entry;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LedgerService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->adminUser = User::factory()->create(['phone' => '700000001', 'role' => 'buyer']);
    $this->adminUser->assignRole('admin');

    $this->sellerUser = User::factory()->create(['phone' => '700000002', 'role' => 'seller:approved']);
    $this->sellerUser->assignRole('seller');
    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'APPROVED',
    ]);

    $this->buyerUser = User::factory()->create(['phone' => '700000003', 'role' => 'buyer']);
    $this->buyerUser->assignRole('buyer');

    $this->agentUser = User::factory()->create(['phone' => '700000004', 'role' => 'agent:approved']);
    $this->agentUser->assignRole('agent');
    $this->agent = Agent::factory()->create([
        'user_id' => $this->agentUser->id,
        'kyc_status' => 'APPROVED',
    ]);
});

function makeFundsLockedOrder(User $buyerUser, Seller $seller, Agent $agent): Order
{
    return Order::factory()->create([
        'buyer_id' => $buyerUser->id,
        'seller_id' => $seller->id,
        'agent_id' => $agent->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
        'expiry_at' => now()->addMinutes(5),
    ]);
}

// --- AuditService Tests ---

test('audit_service_logs_an_entry', function () {
    $service = app(AuditService::class);

    $log = $service->log(
        action: 'order.created',
        entity: 'order',
        entityId: 1,
        user: $this->adminUser,
        details: ['price' => 50000],
    );

    expect($log->exists)->toBeTrue();
    expect($log->action)->toBe('order.created');
    expect($log->entity)->toBe('order');
    expect($log->entity_id)->toBe(1);
    expect($log->details['price'])->toBe(50000);
});

test('audit_service_logs_without_user', function () {
    $service = app(AuditService::class);

    $log = $service->log(
        action: 'order.expired',
        entity: 'order',
        entityId: 1,
        user: null,
    );

    expect($log->exists)->toBeTrue();
    expect($log->user_id)->toBeNull();
});

test('audit_service_logs_authenticated_user_automatically', function () {
    actingAs($this->adminUser);

    $service = app(AuditService::class);

    $log = $service->log(
        action: 'withdrawal.requested',
        entity: 'withdrawal',
        entityId: 1,
    );

    expect($log->exists)->toBeTrue();
    expect($log->user_id)->toBe($this->adminUser->id);
});

test('audit_service_queries_with_filters', function () {
    $service = app(AuditService::class);

    $service->log(action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser);
    $service->log(action: 'order.declined', entity: 'order', entityId: 2, user: $this->adminUser);
    $service->log(action: 'wallet.topup_initiated', entity: 'wallet', user: $this->adminUser);

    $result = $service->query(['action' => 'order.created']);

    expect($result->count())->toBe(1);
    expect($result->first()->action)->toBe('order.created');
});

test('audit_service_summary_counts_by_action', function () {
    $service = app(AuditService::class);

    $service->log(action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser);
    $service->log(action: 'order.created', entity: 'order', entityId: 2, user: $this->adminUser);
    $service->log(action: 'order.declined', entity: 'order', entityId: 3, user: $this->adminUser);

    $summary = $service->summary();

    expect($summary->where('action', 'order.created')->first()->count)->toBe(2);
    expect($summary->where('action', 'order.declined')->first()->count)->toBe(1);
});

// --- Admin API Tests ---

test('admin_can_view_activity_logs', function () {
    app(AuditService::class)->log(
        action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser,
    );

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/activity-logs');

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['data', 'current_page', 'last_page']]);
});

test('admin_cannot_view_activity_logs_without_permission', function () {
    app(AuditService::class)->log(
        action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser,
    );

    $response = actingAs($this->buyerUser)
        ->getJson('/api/admin/activity-logs');

    $response->assertStatus(403);
});

test('admin_can_filter_logs_by_action', function () {
    $service = app(AuditService::class);
    $service->log(action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser);
    $service->log(action: 'order.declined', entity: 'order', entityId: 2, user: $this->adminUser);

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/activity-logs?action=order.created');

    $response->assertOk();
    $logs = $response->json('data.data');
    expect($logs)->not->toBeEmpty();
    foreach ($logs as $log) {
        expect($log['action'])->toBe('order.created');
    }
});

test('admin_can_filter_logs_by_date_range', function () {
    $service = app(AuditService::class);
    $oldLog = $service->log(action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser);
    DB::table('audit_logs')->where('id', $oldLog->id)->update([
        'created_at' => now()->subDays(5),
    ]);

    $recentLog = $service->log(action: 'order.declined', entity: 'order', entityId: 2, user: $this->adminUser);

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/activity-logs?date_from='.$recentLog->created_at->format('Y-m-d'));

    $response->assertOk();
    $data = $response->json('data.data');
    expect(count($data))->toBe(1);
    expect($data[0]['action'])->toBe('order.declined');
});

test('admin_can_view_activity_summary', function () {
    $service = app(AuditService::class);
    $service->log(action: 'order.created', entity: 'order', entityId: 1, user: $this->adminUser);
    $service->log(action: 'order.created', entity: 'order', entityId: 2, user: $this->adminUser);
    $service->log(action: 'order.declined', entity: 'order', entityId: 3, user: $this->adminUser);

    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/activity-summary');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
});

test('audit_middleware_logs_admin_request', function () {
    $response = actingAs($this->adminUser)
        ->getJson('/api/admin/activity-summary');

    $response->assertOk();

    $log = AuditLog::where('action', 'api_request')->first();
    expect($log)->not->toBeNull();
    expect($log->details['method'])->toBe('GET');
    expect($log->details['path'])->toContain('activity-summary');
});

// --- Integration Tests ---

test('order_created_is_logged', function () {
    $user = $this->sellerUser;

    $response = actingAs($user)->postJson('/api/orders/seller-initiated', [
        'seller_id' => $this->seller->id,
        'buyer_phone' => $this->buyerUser->phone,
        'item_description' => 'Test item',
        'price' => 20000,
        'delivery_type' => 'shop_delivery',
        'delivery_location' => 'Nairobi',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'order.created',
        'entity' => 'order',
    ]);
});

test('order_declined_is_logged', function () {
    $order = makeFundsLockedOrder($this->buyerUser, $this->seller, $this->agent);

    $response = actingAs($this->sellerUser)->postJson("/api/orders/{$order->id}/decline", [
        'reason' => 'Out of stock',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'order.declined',
        'entity' => 'order',
        'entity_id' => $order->id,
    ]);

    $log = AuditLog::where('action', 'order.declined')
        ->where('entity_id', $order->id)
        ->first();
    expect($log->details['reason'])->toBe('Out of stock');
});

test('admin_reversal_retry_is_logged', function () {
    Queue::fake();

    $order = Order::factory()->create([
        'buyer_id' => $this->buyerUser->id,
        'seller_id' => $this->seller->id,
        'status' => 'funds_locked',
        'initiator_type' => 'buyer',
        'price' => 50000,
        'flat_fee' => 5000,
        'delivery_type' => 'shop_delivery',
        'expiry_at' => now()->addMinutes(5),
        'reversal_failed_at' => now()->subHour(),
        'reversal_failure_reason' => 'M-Pesa API timeout',
        'reversal_attempts' => 1,
        'mpesa_transaction_id' => 'NFC4JN6Q1G',
    ]);

    $response = actingAs($this->adminUser)
        ->postJson("/api/admin/orders/{$order->id}/retry-reversal");

    $response->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.order.reversal_retried',
        'entity' => 'order',
        'entity_id' => $order->id,
    ]);
});

test('expiry_job_logs_system_event', function () {
    $service = app(AuditService::class);

    $service->log(
        action: 'order.expired',
        entity: 'order',
        entityId: 1,
        user: null,
        details: ['status_before' => 'pending_accept'],
    );

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'order.expired',
        'entity' => 'order',
        'user_id' => null,
    ]);
});

test('audit_log_has_no_updated_at', function () {
    expect(AuditLog::UPDATED_AT)->toBeNull();

    $service = app(AuditService::class);
    $log = $service->log(
        action: 'order.created',
        entity: 'order',
        entityId: 1,
        user: $this->adminUser,
    );

    expect($log->created_at)->not->toBeNull();
    expect($log->getAttribute('updated_at'))->toBeNull();
});

test('withdrawal_request_is_logged', function () {
    $service = app(AuditService::class);
    $ledger = app(LedgerService::class);

    $receivableAccount = $ledger->getOrCreateSellerReceivableAccount($this->seller);
    $mpesaFloat = Account::where('account_code', 'MPESA_FLOAT')->firstOrFail();

    $tx = Transaction::create([
        'transaction_type' => 'deposit',
        'reference_type' => 'manual',
        'description' => 'Seed receivable balance',
        'status' => 'completed',
    ]);

    Entry::create([
        'transaction_id' => $tx->id,
        'account_id' => $mpesaFloat->id,
        'debit_amount' => 50000,
        'credit_amount' => 0,
        'balance_after' => 50000,
    ]);

    Entry::create([
        'transaction_id' => $tx->id,
        'account_id' => $receivableAccount->id,
        'debit_amount' => 0,
        'credit_amount' => 50000,
        'balance_after' => 50000,
    ]);

    $response = actingAs($this->sellerUser)->postJson('/api/withdrawals/request', [
        'amount_cents' => 10000,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'withdrawal.requested',
        'entity' => 'withdrawal',
    ]);
});
