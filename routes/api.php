<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'Pro_BMS API is working!',
        'status'  => 'success',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Inventory
    Route::prefix('inventory')->group(function () {
        Route::get('/stats',             [ProductController::class,  'stats']);
        Route::get('/low-stock',         [ProductController::class,  'lowStock']);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products',   ProductController::class);
    });

    // Sales
    Route::prefix('sales')->group(function () {
        Route::get('/stats',            [OrderController::class,    'stats']);
        Route::get('/customer-stats',   [CustomerController::class, 'stats']);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('orders',    OrderController::class);
    });

    // Purchasing
    Route::prefix('purchasing')->group(function () {
        Route::get('/stats',            [PurchaseOrderController::class, 'stats']);
        Route::get('/supplier-stats',   [SupplierController::class,      'stats']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('orders',    PurchaseOrderController::class);
    });

});