<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\ChatPageController;
use App\Models\Order;
use App\Models\OrderTemplate;
use App\Models\Withdrawal;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['mobile.verified'])->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');

        // Seller upgrade page
        Route::inertia('seller/request', 'seller/request')->name('seller.request');

        // Agent upgrade page
        Route::inertia('agent/request', 'agent/request')->name('agent.request');

        // Agent dashboard
        Route::inertia('agent/dashboard', 'agent/dashboard')->name('agent.dashboard');

        // Wallet page
        Route::get('wallet', function (Request $request, LedgerService $ledgerService) {
            $user = $request->user();
            $balance = $ledgerService->getBuyerBalance($user);
            $account = $ledgerService->getOrCreateBuyerWalletAccount($user);
            $history = $ledgerService->getAccountHistory($account);

            return Inertia::render('wallet/index', [
                'wallet_balance' => ['available' => $balance, 'ledger' => $balance],
                'recent_transactions' => $history,
            ]);
        })->name('wallet');

        // Orders pages
        Route::get('orders', function (Request $request) {
            $user = $request->user();
            $orders = Order::with(['seller', 'buyer'])
                ->where(function ($query) use ($user) {
                    $query->where('buyer_id', $user->id)
                        ->orWhereHas('seller', function ($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return Inertia::render('orders/index', ['orders' => $orders]);
        })->name('orders.index');

        Route::get('orders/create/seller', function (Request $request) {
            $user = $request->user();
            $seller = $user->sellers()->first();
            $templates = $seller
                ? OrderTemplate::where('seller_id', $seller->id)->get()
                : [];

            return Inertia::render('orders/create-seller', [
                'templates' => $templates,
                'seller_id' => $seller?->id,
            ]);
        })->name('orders.create-seller');

        Route::inertia('orders/create/buyer', 'orders/create-buyer')->name('orders.create-buyer');
        Route::get('orders/{order}', function (Order $order) {
            $order->load(['seller', 'buyer', 'agent', 'issueReports', 'chatMessages']);

            return Inertia::render('orders/show', ['order' => $order]);
        })->name('orders.show');

        // Seller templates page
        Route::get('seller/templates', function (Request $request) {
            $user = $request->user();
            $seller = $user->sellers()->first();
            $templates = $seller
                ? OrderTemplate::where('seller_id', $seller->id)->get()
                : [];

            return Inertia::render('seller/templates', ['templates' => $templates]);
        })->name('seller.templates');

        // Chat page
        Route::get('orders/{order}/chat', [ChatPageController::class, 'show'])->name('orders.chat');

        // Seller withdrawals page
        Route::get('seller/withdrawals', function (Request $request, LedgerService $ledgerService) {
            $user = $request->user();
            $seller = $user->sellers()->first();
            $receivableBalance = 0;
            $withdrawals = collect();

            if ($seller) {
                $receivableAccount = $ledgerService->getOrCreateSellerReceivableAccount($seller);
                $receivableBalance = $receivableAccount->balance;
                $withdrawals = Withdrawal::where('seller_id', $seller->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return Inertia::render('seller/withdrawals', [
                'receivable_balance' => $receivableBalance,
                'withdrawals' => $withdrawals,
            ]);
        })->name('seller.withdrawals');
    });

    require __DIR__.'/settings.php';
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::inertia('sellers/pending', 'sellers/pending')->name('sellers.pending');
    Route::inertia('sellers/{seller}/review', 'sellers/review')->name('sellers.review');
    Route::inertia('agents/pending', 'agents/pending')->name('agents.pending');
    Route::inertia('agents/{agent}/review', 'agents/review')->name('agents.review');

    // Admin withdrawals page
    Route::get('withdrawals', function (Request $request) {
        $withdrawals = Withdrawal::with('seller.user')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('admin/withdrawals/index', [
            'withdrawals' => $withdrawals,
        ]);
    })->name('withdrawals');

    // Failed reversals page
    Route::get('failed-reversals', function (Request $request) {
        $orders = Order::whereNotNull('reversal_failed_at')
            ->whereNull('reversal_resolved_at')
            ->with(['buyer', 'seller'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('admin/failed-reversals/index', ['orders' => $orders]);
    })->name('failed-reversals');

    // Activity logs page
    Route::get('activity-logs', function (Request $request) {
        $summary = app(AuditService::class)->summary();

        return Inertia::render('admin/activity-logs/index', [
            'initial_summary' => $summary,
        ]);
    })->name('activity-logs');

    // Callback monitoring page
    Route::inertia('callbacks', 'admin/callbacks/index')->name('callbacks');

    // Issue reports page
    Route::get('issue-reports', function () {
        return Inertia::render('admin/issue-reports/index', [
            'statuses' => ['REPORTED', 'UNDER_REVIEW', 'RESOLVED', 'DISMISSED'],
            'issue_types' => ['QUANTITY_MISMATCH', 'QUALITY_ISSUE', 'NOT_AS_DESCRIBED', 'OTHER'],
        ]);
    })->name('issue-reports');

    // Settings page
    Route::get('settings', function (Request $request) {
        $settings = app(SettingsService::class)->all();

        return Inertia::render('admin/settings/index', [
            'settings' => $settings,
        ]);
    })->name('settings');
});

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('otp/send', [OtpController::class, 'send'])->name('otp.send');
    Route::post('otp/send-login', [OtpController::class, 'sendLogin'])->name('otp.sendLogin');
    Route::post('otp/verify', [OtpController::class, 'verify'])->name('otp.verify');

    Route::get('otp-login', fn () => Inertia::render('auth/otp-login', [
        'canRegister' => Features::enabled(Features::registration()),
    ]))->name('otp.login.page');

    Route::get('otp-verify', function (Request $request) {
        $phone = $request->session()->get('otp_verify_phone');

        if (! $phone) {
            return redirect()->to('/login');
        }

        return Inertia::render('auth/otp-verify', [
            'phone' => $phone,
            'type' => $request->session()->get('otp_verify_type', 'phone_verification'),
            'reason' => $request->session()->get('otp_verify_reason', ''),
        ]);
    })->name('otp.verify.page');
});
