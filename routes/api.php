<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\GviStockController;
use App\Http\Controllers\Api\TransactionController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

// Webhook Midtrans — tidak pakai auth (Midtrans yang hit ini)
Route::post('/webhook/midtrans', [TransactionController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

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
});
