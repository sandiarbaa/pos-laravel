<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\GviStockController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

// Webhook Midtrans — tidak pakai auth (Midtrans yang hit ini)
Route::post('/webhook/midtrans', [TransactionController::class, 'webhook']);

// Export Reporting in Browser
Route::get('/transactions/export', [TransactionController::class, 'export']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User management (superadmin kelola admin, admin kelola kasir)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Businesses
    Route::apiResource('businesses', BusinessController::class);

    // Products (POS sendiri)
    Route::apiResource('products', ProductController::class);

    // GVI-Stock proxy (Flutter hit POS Laravel, POS Laravel hit GVI-Stock)
    Route::prefix('gvi')->group(function () {
        Route::get('/item-types', [GviStockController::class, 'itemTypes']);
        Route::get('/item-variants', [GviStockController::class, 'itemVariants']);
        Route::get('/item-variants/{id}', [GviStockController::class, 'itemVariantDetail']);
    });

    // Transactions
    Route::get('/transactions/today-summary', [TransactionController::class, 'todaySummary']);
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
    Route::put('/transactions/{id}/cancel', [TransactionController::class, 'cancel']);
    // Route::get('/transactions/export', [TransactionController::class, 'export']);
});
