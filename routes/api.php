<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AnalyticsController;
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

    // HR
    Route::prefix('hr')->group(function () {
        Route::get('/stats',                        [EmployeeController::class,    'stats']);
        Route::get('/departments',                  [EmployeeController::class,    'departments']);
        Route::get('/job-stats',                    [JobPositionController::class, 'stats']);
        Route::get('/candidate-stats',              [CandidateController::class,   'stats']);
        Route::post('/candidates/{candidate}/hire', [CandidateController::class,   'hire']);
        Route::apiResource('employees',             EmployeeController::class);
        Route::apiResource('job-positions',         JobPositionController::class);
        Route::apiResource('candidates',            CandidateController::class);
    });

    // Accounting
    Route::prefix('accounting')->group(function () {
        Route::get('/stats',          [TransactionController::class, 'stats']);
        Route::get('/monthly-report', [TransactionController::class, 'monthlyReport']);
        Route::apiResource('transactions', TransactionController::class);
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/overview',         [AnalyticsController::class, 'overview']);
        Route::get('/sales-chart',      [AnalyticsController::class, 'salesChart']);
        Route::get('/expenses-chart',   [AnalyticsController::class, 'expensesChart']);
        Route::get('/top-products',     [AnalyticsController::class, 'topProducts']);
        Route::get('/top-customers',    [AnalyticsController::class, 'topCustomers']);
        Route::get('/order-status',     [AnalyticsController::class, 'orderStatusChart']);
        Route::get('/inventory-chart',  [AnalyticsController::class, 'inventoryChart']);
    });

});