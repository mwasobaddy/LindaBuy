<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\SellerController;
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
    });
});
