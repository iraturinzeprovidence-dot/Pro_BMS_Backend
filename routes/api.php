<?php
use App\Http\Controllers\PdfController;
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
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'Pro BMS API is working!',
        'status'  => 'success',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});
// Public routes — no login required
Route::prefix('public')->group(function () {

    // Get open job positions
    Route::get('/jobs', function () {
        $jobs = \App\Models\JobPosition::where('status', 'open')
            ->select('id', 'title', 'department', 'type', 'salary_min', 'salary_max', 'deadline', 'description', 'requirements')
            ->get();
        return response()->json($jobs);
    });

    // Submit job application
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
            return response()->json([
                'message' => 'You have already applied for this position.'
            ], 422);
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
            'message'   => 'Application submitted successfully! We will contact you soon.',
            'candidate' => $candidate,
        ], 201);
    });

    // Customer self-registration
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
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'address'  => $request->address,
            'city'     => $request->city,
            'country'  => $request->country,
            'status'   => 'active',
        ]);

        // Also create a user account for the customer
        $role = \App\Models\Role::where('name', 'employee')->first();
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

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Inventory — admin and manager
    Route::prefix('inventory')->middleware('role:admin,manager')->group(function () {
        Route::get('/stats',             [ProductController::class, 'stats']);
        Route::get('/low-stock',         [ProductController::class, 'lowStock']);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products',   ProductController::class);
    });

    // Sales — admin and manager
    Route::prefix('sales')->middleware('role:admin,manager')->group(function () {
        Route::get('/stats',            [OrderController::class,    'stats']);
        Route::get('/customer-stats',   [CustomerController::class, 'stats']);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('orders',    OrderController::class);
    });

    // Purchasing — admin only
    Route::prefix('purchasing')->middleware('role:admin')->group(function () {
        Route::get('/stats',            [PurchaseOrderController::class, 'stats']);
        Route::get('/supplier-stats',   [SupplierController::class,      'stats']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('orders',    PurchaseOrderController::class);
    });

    // HR — admin and manager
 Route::prefix('hr')->middleware('role:admin,manager')->group(function () {
    Route::get('/stats',                        [EmployeeController::class,    'stats']);
    Route::get('/departments',                  [EmployeeController::class,    'departments']);
    Route::get('/job-stats',                    [JobPositionController::class, 'stats']);
    Route::get('/candidate-stats',              [CandidateController::class,   'stats']);
    Route::post('/candidates/{candidate}/hire', [CandidateController::class,   'hire']);
    Route::apiResource('employees',             EmployeeController::class);
    Route::apiResource('job-positions',         JobPositionController::class);
    Route::apiResource('candidates',            CandidateController::class);
});

// Employee self profile — accessible by all roles
Route::get('/hr/my-profile', [EmployeeController::class, 'myProfile']);

    // Accounting — admin only
    Route::prefix('accounting')->middleware('role:admin')->group(function () {
        Route::get('/stats',               [TransactionController::class, 'stats']);
        Route::get('/monthly-report',      [TransactionController::class, 'monthlyReport']);
        Route::apiResource('transactions', TransactionController::class);
    });

    // Analytics — admin and manager
    Route::prefix('analytics')->middleware('role:admin,manager')->group(function () {
        Route::get('/overview',        [AnalyticsController::class, 'overview']);
        Route::get('/sales-chart',     [AnalyticsController::class, 'salesChart']);
        Route::get('/expenses-chart',  [AnalyticsController::class, 'expensesChart']);
        Route::get('/top-products',    [AnalyticsController::class, 'topProducts']);
        Route::get('/top-customers',   [AnalyticsController::class, 'topCustomers']);
        Route::get('/order-status',    [AnalyticsController::class, 'orderStatusChart']);
        Route::get('/inventory-chart', [AnalyticsController::class, 'inventoryChart']);
    });

    // User Management — admin only
    Route::prefix('users')->middleware('role:admin')->group(function () {
        Route::get('/',          [UserController::class, 'index']);
        Route::post('/',         [UserController::class, 'store']);
        Route::put('/{user}',    [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::get('/stats',     [UserController::class, 'stats']);
    });
// PDF Export
Route::prefix('pdf')->middleware('role:admin,manager')->group(function () {
    Route::get('/order/{order}',  [PdfController::class, 'exportOrder']);
    Route::get('/transactions',   [PdfController::class, 'exportTransactions']);
    Route::get('/employees',      [PdfController::class, 'exportEmployees']);
});

});