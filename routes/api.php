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
        'cv'              => 'nullable|file|mimes:pdf|max:5120',
        'certificate'     => 'nullable|file|mimes:pdf|max:5120',
        'id_document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        'passport_photo'  => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
    ]);

    $existing = \App\Models\Candidate::where('email', $request->email)
        ->where('job_position_id', $request->job_position_id)
        ->first();

    if ($existing) {
        return response()->json(['message' => 'You have already applied for this position.'], 422);
    }

    $data = [
        'job_position_id' => $request->job_position_id,
        'first_name'      => $request->first_name,
        'last_name'       => $request->last_name,
        'email'           => $request->email,
        'phone'           => $request->phone,
        'cover_letter'    => $request->cover_letter,
        'status'          => 'applied',
    ];

    foreach ([
        'cv'           => 'candidates/cv',
        'certificate'  => 'candidates/certificates',
        'id_document'  => 'candidates/id_documents',
        'passport_photo' => 'candidates/passport_photos',
    ] as $field => $folder) {
        if ($request->hasFile($field)) {
            $file = $request->file($field);
            if ($field === 'passport_photo') {
                $data['passport_photo_path'] = $file->store($folder, 'public');
            } else {
                $data[$field . '_path']          = $file->store($folder, 'public');
                $data[$field . '_original_name'] = $file->getClientOriginalName();
            }
        }
    }

    $candidate = \App\Models\Candidate::create($data);

    return response()->json([
        'message'   => 'Application submitted successfully! We will contact you soon.',
        'candidate' => $candidate,
    ], 201);
});
    // Public homepage products
Route::get('/homepage-products', function () {
    $products = \App\Models\Product::with('category')
        ->where('status', 'active')
        ->where('stock', '>', 0)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($p) {
            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'price'       => $p->price,
                'stock'       => $p->stock,
                'image'       => $p->image
                    ? asset('storage/' . $p->image)
                    : null,
                'category'    => $p->category?->name,
                'sku'         => $p->sku,
            ];
        });

    return response()->json($products);
});

// Public categories
Route::get('/homepage-categories', function () {
    $categories = \App\Models\Category::withCount('products')
        ->having('products_count', '>', 0)
        ->get(['id', 'name']);
    return response()->json($categories);
});

    // Forgot password
Route::post('/forgot-password', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'If this email exists we will send a reset link.']);
    }

    $token = \Illuminate\Support\Str::random(64);

    \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $request->email],
        ['email' => $request->email, 'token' => bcrypt($token), 'created_at' => now()]
    );

    // Send email with reset link
    try {
        \Illuminate\Support\Facades\Mail::send(
            [],
            [],
            function ($message) use ($request, $token) {
                $resetUrl = config('app.frontend_url', 'http://localhost:5173') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);
                
                $message->to($request->email)
                    ->subject('Pro_BMS — Password Reset Request')
                    ->html('
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Pro_BMS</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,100..900;1,100..900&display=swap");
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
    <div style="max-width: 520px; margin: 0 auto; padding: 32px 24px;">
        <!-- Main Card -->
        <div style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden;">
            
            <!-- Header with Gradient -->
            <div style="background: linear-gradient(135deg, #047857 0%, #065f46 100%); padding: 36px 24px; text-align: center;">
                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 12px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 7L12 3L4 7L12 11L20 7Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 7V17L12 21L20 17V7" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 11V21" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 8.5L16 12.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">Pro_BMS</h1>
                </div>
                <p style="color: #a7f3d0; margin: 8px 0 0; font-size: 14px; font-weight: 500;">Password Reset Request</p>
            </div>
            
            <!-- Content -->
            <div style="padding: 36px 32px;">
                <!-- Greeting -->
                <p style="color: #1f2937; font-size: 16px; line-height: 1.5; margin-bottom: 20px; font-weight: 500;">
                    Hello,
                </p>
                
                <!-- Message -->
                <p style="color: #4b5563; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                    We received a request to reset the password for your Pro_BMS account. Click the button below to create a new password:
                </p>
                
                <!-- Reset Button -->
                <div style="text-align: center; margin: 32px 0 28px;">
                    <a href="' . $resetUrl . '"
                        style="display: inline-flex; align-items: center; justify-content: center; gap: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); transition: all 0.3s ease;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 3H19C20.1046 3 21 3.89543 21 5V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H9" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M10 14L21 3" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M15 3H21V9" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Reset Password
                    </a>
                </div>
                
                <!-- Alternative Link -->
                <p style="color: #6b7280; font-size: 13px; line-height: 1.5; text-align: center; margin-bottom: 20px;">
                    Or copy this link:<br>
                    <span style="color: #059669; font-size: 12px; word-break: break-all;">' . $resetUrl . '</span>
                </p>
                
                <!-- Warning Box -->
                <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 8px; margin: 24px 0;">
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0; margin-top: 1px;">
                            <path d="M12 8V12M12 16H12.01M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <div>
                            <p style="color: #92400e; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Security Note</p>
                            <p style="color: #b45309; font-size: 12px; margin: 0;">This link will expire in 60 minutes for your security.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Ignore Message -->
                <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; margin: 20px 0 0; text-align: center;">
                    <p style="color: #6b7280; font-size: 12px; margin: 0;">
                        If you didn\'t request this password reset, please ignore this email.
                        <br>No changes will be made to your account.
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f9fafb; padding: 24px 32px; text-align: center; border-top: 1px solid #e5e7eb;">
                <div style="display: flex; justify-content: center; gap: 24px; margin-bottom: 16px;">
                    <a href="#" style="color: #6b7280; text-decoration: none; font-size: 12px;">Help Center</a>
                    <a href="#" style="color: #6b7280; text-decoration: none; font-size: 12px;">Privacy Policy</a>
                    <a href="#" style="color: #6b7280; text-decoration: none; font-size: 12px;">Terms of Use</a>
                </div>
                <p style="color: #9ca3af; font-size: 11px; margin: 0;">
                    &copy; ' . date('Y') . ' Pro_BMS. All rights reserved.
                    <br>Business Management System
                </p>
            </div>
        </div>
        
        <!-- Footer Note -->
        <p style="text-align: center; color: #9ca3af; font-size: 11px; margin-top: 24px;">
            If you have any issues, contact our support team at <a href="mailto:support@probms.com" style="color: #059669; text-decoration: none;">support@probms.com</a>
        </p>
    </div>
</body>
</html>
                    ');
            }
        );
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning('Reset email failed: ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'If this email exists we will send a reset link.',
        'debug_token' => config('app.debug') ? $token : null,
    ]);
});

// Reset password
Route::post('/reset-password', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'email'                 => 'required|email',
        'token'                 => 'required|string',
        'password'              => 'required|string|min:8|confirmed',
    ]);

    $reset = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->first();

    if (!$reset || !\Illuminate\Support\Facades\Hash::check($request->token, $reset->token)) {
        return response()->json(['message' => 'Invalid or expired reset token.'], 422);
    }

    // Check token not older than 60 minutes
    if (\Carbon\Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
        return response()->json(['message' => 'Reset token has expired. Please request a new one.'], 422);
    }

    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found.'], 404);
    }

    $user->update([
        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
    ]);

    \Illuminate\Support\Facades\DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->delete();

    return response()->json(['message' => 'Password reset successfully! You can now login.']);
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
        Route::post('/orders/{order}/mark-paid', [OrderController::class, 'markPaid']);
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
        Route::apiResource('employees',             EmployeeController::class);
        Route::apiResource('job-positions',         JobPositionController::class);
        Route::get('/candidate-stats',              [CandidateController::class, 'stats']);
        Route::post('/candidates/{candidate}/hire', [CandidateController::class, 'hire']);
        Route::get('/candidates/{candidate}/download/{type}', [CandidateController::class, 'downloadFile']);
        Route::get('/candidates/{candidate}/download-photo',  [CandidateController::class, 'downloadPassportPhoto']);
        Route::apiResource('candidates', CandidateController::class);
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