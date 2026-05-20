<?php

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminWithdrawalController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTemplateController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Seller routes
    Route::post('/sellers/request', [SellerController::class, 'store']);
    Route::get('/sellers/my-shops', [SellerController::class, 'myShops']);
    Route::patch('/sellers/{seller}', [SellerController::class, 'update']);

    // Agent routes
    Route::post('/agents/request', [AgentController::class, 'store']);
    Route::patch('/agents/{agent}/update-kyc', [AgentController::class, 'updateKyc']);
    Route::get('/agents/me', [AgentController::class, 'show']);

    // Wallet (authenticated)
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('balance', [WalletController::class, 'balance'])->name('balance');
        Route::post('top-up', [WalletController::class, 'topUp'])->name('top-up');
        Route::get('status/{checkoutRequestId}', [WalletController::class, 'status'])->name('status');
        Route::get('transactions', [WalletController::class, 'depositHistory'])->name('transactions');
    });

    // Withdrawals (seller)
    Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
        Route::post('request', [WithdrawalController::class, 'request'])->name('request');
        Route::get('seller', [WithdrawalController::class, 'myWithdrawals'])->name('seller');
    });

    // Orders (authenticated)
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'myOrders'])->name('index');
        Route::get('available-jobs', [OrderController::class, 'agentAvailableJobs'])->name('available-jobs');
        Route::get('my-jobs', [OrderController::class, 'agentMyJobs'])->name('my-jobs');
        Route::get('{order}', [OrderController::class, 'show'])->name('show');
        Route::post('seller-initiated', [OrderController::class, 'createSellerOrder'])->name('create-seller');
        Route::post('buyer-initiated', [OrderController::class, 'createBuyerOrder'])->name('create-buyer');
        Route::post('{order}/accept', [OrderController::class, 'accept'])->name('accept');
        Route::post('{order}/decline', [OrderController::class, 'decline'])->name('decline');
        Route::post('{order}/confirm-delivery', [OrderController::class, 'confirmDelivery'])->name('confirm-delivery');
        Route::post('{order}/request-release-confirmation', [OrderController::class, 'requestReleaseConfirmation'])->name('request-release');
        Route::post('{order}/resend-release-token', [OrderController::class, 'resendReleaseToken'])->name('resend-release');
        Route::post('{order}/release', [OrderController::class, 'release'])->name('release');
        Route::post('{order}/accept-job', [OrderController::class, 'acceptJob'])->name('accept-job');
        Route::post('{order}/verify', [OrderController::class, 'verify'])->name('verify');
        Route::post('{order}/report-issue', [OrderController::class, 'reportIssue'])->name('report-issue');

        // Chat messages
        Route::get('{order}/messages', [ChatController::class, 'index'])->name('messages.index');
        Route::post('{order}/messages', [ChatController::class, 'store'])->name('messages.store');
    });

    // Order Templates (seller only)
    Route::middleware('permission:create-sell-orders')->prefix('order-templates')->name('order-templates.')->group(function () {
        Route::get('/', [OrderTemplateController::class, 'index'])->name('index');
        Route::post('/', [OrderTemplateController::class, 'store'])->name('store');
        Route::get('{orderTemplate}', [OrderTemplateController::class, 'show'])->name('show');
        Route::patch('{orderTemplate}', [OrderTemplateController::class, 'update'])->name('update');
        Route::delete('{orderTemplate}', [OrderTemplateController::class, 'destroy'])->name('destroy');
    });

    // Admin routes
    Route::prefix('admin')->middleware(['admin'])->group(function () {
        // Sellers
        Route::get('/sellers/pending', [SellerController::class, 'pendingSellers']);
        Route::get('/sellers', [SellerController::class, 'allSellers']);
        Route::patch('/sellers/{seller}/approve-kyc', [SellerController::class, 'approveKyc']);
        Route::patch('/sellers/{seller}/reject-kyc', [SellerController::class, 'rejectKyc']);
        Route::patch('/sellers/{seller}/approve-shop', [SellerController::class, 'approveShop']);
        Route::patch('/sellers/{seller}/reject-shop', [SellerController::class, 'rejectShop']);
        Route::patch('/sellers/{seller}/toggle-status', [SellerController::class, 'toggleSellerStatus']);

        // Agents
        Route::get('/agents/pending', [AgentController::class, 'pendingAgents']);
        Route::get('/agents', [AgentController::class, 'allAgents']);
        Route::patch('/agents/{agent}/approve', [AgentController::class, 'approve']);
        Route::patch('/agents/{agent}/reject', [AgentController::class, 'reject']);
        Route::patch('/agents/{agent}/toggle-status', [AgentController::class, 'toggleAgentStatus']);

        // Withdrawals (admin)
        Route::get('withdrawals', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('withdrawals/{withdrawal}/process', [AdminWithdrawalController::class, 'process'])->name('withdrawals.process');
        Route::post('withdrawals/{withdrawal}/complete', [AdminWithdrawalController::class, 'complete'])->name('withdrawals.complete');
        Route::post('withdrawals/{withdrawal}/fail', [AdminWithdrawalController::class, 'fail'])->name('withdrawals.fail');

        // Admin orders
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::patch('orders/{order}/confirm-g4s-pickup', [AdminOrderController::class, 'confirmG4sPickup']);
        Route::patch('orders/{order}/auto-release', [AdminOrderController::class, 'setAutoRelease']);
        Route::get('g4s-pending-release', [AdminOrderController::class, 'g4sPendingRelease']);
        Route::get('orders/{order}/g4s-details', [AdminOrderController::class, 'g4sDetails']);
    });
});

// M-Pesa callback (no auth — IP allowlist)
Route::post('wallet/callback', [WalletController::class, 'callback'])
    ->middleware('mpesa-ip')
    ->name('wallet.callback');
