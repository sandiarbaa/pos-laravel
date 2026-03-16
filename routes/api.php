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
Route::post('/webhook/midtrans', [TransactionController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
// Export (di luar sanctum karena dibuka di browser — auth via query token)
Route::get('/transactions/export', [TransactionController::class, 'export']);

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User management (superadmin kelola admin, admin kelola kasir)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/users/{id}', [UserController::class, 'update']); // multipart photo support
    Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Businesses
    Route::apiResource('businesses', BusinessController::class);
    Route::post('/businesses/{business}', [BusinessController::class, 'update']); // multipart upload support

    // Products (POS sendiri)
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}', [ProductController::class, 'update']); // multipart upload support

    // GVI-Stock proxy
    Route::prefix('gvi')->group(function () {
        Route::get('/item-types', [GviStockController::class, 'itemTypes']);
        Route::get('/item-variants', [GviStockController::class, 'itemVariants']);
        Route::get('/item-variants/{id}', [GviStockController::class, 'itemVariantDetail']);
    });

    // Transactions
    Route::get('/transactions/today-summary', [TransactionController::class, 'todaySummary']);
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
    Route::put('/transactions/{id}/cancel', [TransactionController::class, 'cancel']);
    Route::post('/transactions/cancel-direct', [TransactionController::class, 'storeCancelled']);
});
