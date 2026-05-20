<?php

use App\Http\Controllers\Api\Admin\AdminWithdrawalController;
use App\Http\Controllers\Api\AgentController;
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
    });
});

// M-Pesa callback (no auth — IP allowlist)
Route::post('wallet/callback', [WalletController::class, 'callback'])
    ->middleware('mpesa-ip')
    ->name('wallet.callback');
