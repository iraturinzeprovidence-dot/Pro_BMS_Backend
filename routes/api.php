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
use App\Http\Controllers\UserController;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

// Test route
Route::get('/test', function () {
    return response()->json([
        'message' => 'Pro_BMS API is working!',
        'status'  => 'success',
    ]);
});

// Customer Portal — authenticated customers
Route::prefix('customer')->middleware('auth:sanctum')->group(function () {
    Route::get('/products',      function () {
        $products = \App\Models\Product::with('category')
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->get();
        return response()->json($products);
    });

    Route::post('/order', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,bank_transfer,card,other',
            'notes'          => 'nullable|string',
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $user     = $request->user();
            $customer = \App\Models\Customer::where('email', $user->email)->first();
            $subtotal = 0;

            foreach ($request->items as $item) {
                $product   = \App\Models\Product::find($item['product_id']);
                $subtotal += $item['quantity'] * $product->price;
            }

            $order = \App\Models\Order::create([
                'order_number'   => 'ORD-' . strtoupper(uniqid()),
                'customer_id'    => $customer?->id,
                'user_id'        => $user->id,
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $request->payment_method,
                'subtotal'       => $subtotal,
                'tax'            => 0,
                'discount'       => 0,
                'total'          => $subtotal,
                'notes'          => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                \App\Models\OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $product->price,
                    'total'        => $item['quantity'] * $product->price,
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'message'      => 'Order placed successfully!',
                'order_number' => $order->order_number,
                'total'        => $order->total,
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['message' => 'Failed to place order: ' . $e->getMessage()], 500);
        }
    });

    Route::get('/my-orders', function (\Illuminate\Http\Request $request) {
        $user     = $request->user();
        $customer = \App\Models\Customer::where('email', $user->email)->first();

        if (!$customer) {
            return response()->json([]);
        }

        $orders = \App\Models\Order::with('items')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    });
});

// Public routes
Route::prefix('public')->group(function () {
    Route::get('/jobs', function () {
        $jobs = \App\Models\JobPosition::where('status', 'open')
            ->select('id', 'title', 'department', 'type', 'salary_min', 'salary_max', 'deadline', 'description', 'requirements')
            ->get();
        return response()->json($jobs);
    });

    Route::post('/apply', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'job_position_id' => 'required|exists:job_positions,id',
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|email',
            'phone'           => 'nullable|string|max:20',
            'cover_letter'    => 'nullable|string',
        ]);

        $existing = \App\Models\Candidate::where('email', $request->email)
            ->where('job_position_id', $request->job_position_id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already applied for this position.'], 422);
        }

        $candidate = \App\Models\Candidate::create([
            'job_position_id' => $request->job_position_id,
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'phone'           => $request->phone,
            'cover_letter'    => $request->cover_letter,
            'status'          => 'applied',
        ]);

        return response()->json([
            'message'   => 'Application submitted successfully!',
            'candidate' => $candidate,
        ], 201);
    });

    Route::post('/customer-register', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:customers',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string',
            'city'     => 'nullable|string',
            'country'  => 'nullable|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $customer = \App\Models\Customer::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'phone'   => $request->phone,
            'address' => $request->address,
            'city'    => $request->city,
            'country' => $request->country,
            'status'  => 'active',
        ]);

$role = \App\Models\Role::where('name', 'customer')->first();
        $user = \App\Models\User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role_id'  => $role?->id,
        ]);

        $token = $user->createToken('pro_bms_token')->plainTextToken;

        return response()->json([
            'message'  => 'Account created successfully!',
            'token'    => $token,
            'customer' => $customer,
            'user'     => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => 'employee',
            ],
        ], 201);
    });
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Employee self-profile — all authenticated users
    Route::get('/hr/my-profile', [EmployeeController::class, 'myProfile']);

    // Inventory — admin, manager, or employee with inventory permission
    Route::prefix('inventory')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/stats',             [ProductController::class,  'stats']);
        Route::get('/low-stock',         [ProductController::class,  'lowStock']);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products',   ProductController::class);
    });

    // Sales — admin, manager, or employee with sales permission
    Route::prefix('sales')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/stats',            [OrderController::class,    'stats']);
        Route::get('/customer-stats',   [CustomerController::class, 'stats']);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('orders',    OrderController::class);
    });

    // Image uploads
Route::post('/upload/avatar',           [ImageController::class, 'uploadAvatar']);
Route::post('/upload/product/{product}',[ImageController::class, 'uploadProductImage']);

    // Purchasing — admin, manager, or employee with purchasing permission
    Route::prefix('purchasing')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/stats',            [PurchaseOrderController::class, 'stats']);
        Route::get('/supplier-stats',   [SupplierController::class,      'stats']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('orders',    PurchaseOrderController::class);
    });

    // HR — admin, manager, or employee with hr permission
    Route::prefix('hr')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/stats',                        [EmployeeController::class,    'stats']);
        Route::get('/departments',                  [EmployeeController::class,    'departments']);
        Route::get('/job-stats',                    [JobPositionController::class, 'stats']);
        Route::get('/candidate-stats',              [CandidateController::class,   'stats']);
        Route::post('/candidates/{candidate}/hire', [CandidateController::class,   'hire']);
        Route::apiResource('employees',             EmployeeController::class);
        Route::apiResource('job-positions',         JobPositionController::class);
        Route::apiResource('candidates',            CandidateController::class);
    });

    // Accounting — admin, manager, or employee with accounting permission
    Route::prefix('accounting')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/stats',               [TransactionController::class, 'stats']);
        Route::get('/monthly-report',      [TransactionController::class, 'monthlyReport']);
        Route::apiResource('transactions', TransactionController::class);
    });

    // Analytics — admin, manager, or employee with analytics permission
    Route::prefix('analytics')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/overview',        [AnalyticsController::class, 'overview']);
        Route::get('/sales-chart',     [AnalyticsController::class, 'salesChart']);
        Route::get('/expenses-chart',  [AnalyticsController::class, 'expensesChart']);
        Route::get('/top-products',    [AnalyticsController::class, 'topProducts']);
        Route::get('/top-customers',   [AnalyticsController::class, 'topCustomers']);
        Route::get('/order-status',    [AnalyticsController::class, 'orderStatusChart']);
        Route::get('/inventory-chart', [AnalyticsController::class, 'inventoryChart']);
    });

    // PDF Export
    Route::prefix('pdf')->middleware('role:admin,manager,employee')->group(function () {
        Route::get('/order/{order}',  [PdfController::class, 'exportOrder']);
        Route::get('/transactions',   [PdfController::class, 'exportTransactions']);
        Route::get('/employees',      [PdfController::class, 'exportEmployees']);
    });

    // User Management — admin only
    Route::prefix('users')->middleware('role:admin')->group(function () {
        Route::get('/',          [UserController::class, 'index']);
        Route::post('/',         [UserController::class, 'store']);
        Route::put('/{user}',    [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::get('/stats',     [UserController::class, 'stats']);
    });

});