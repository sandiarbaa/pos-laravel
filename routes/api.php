<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\BusinessTaxController;
use App\Http\Controllers\Api\FoodDetectionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\GviStockController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\NutritionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;

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

    Route::get('/businesses/{business}/taxes', [BusinessTaxController::class, 'index']);
    Route::post('/businesses/{business}/taxes', [BusinessTaxController::class, 'store']);
    Route::put('/businesses/{business}/taxes/{tax}', [BusinessTaxController::class, 'update']);
    Route::delete('/businesses/{business}/taxes/{tax}', [BusinessTaxController::class, 'destroy']);
    Route::patch('/businesses/{business}/taxes/{tax}/toggle', [BusinessTaxController::class, 'toggle']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Products (POS sendiri)
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}', [ProductController::class, 'update']); // multipart upload support

    // Nutrition
    Route::get('/products/{productId}/nutrition', [NutritionController::class, 'show']);
    Route::post('/products/{productId}/nutrition/generate', [NutritionController::class, 'generate']);
    Route::put('/products/{productId}/nutrition', [NutritionController::class, 'update']);

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

    // Food Detection
    Route::post('/detect-food', [FoodDetectionController::class, 'detect']);
});

Route::prefix('kitchen')->group(function () {
    Route::get('/queue',              [KitchenController::class, 'queue']);
    Route::patch('/items/{id}/start', [KitchenController::class, 'start']);
    Route::patch('/items/{id}/pause', [KitchenController::class, 'pause']);
    Route::patch('/items/{id}/done',  [KitchenController::class, 'done']);
});
