<?php

use App\Http\Controllers\Auth\OtpController;
use App\Models\Withdrawal;
use App\Services\LedgerService;
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
