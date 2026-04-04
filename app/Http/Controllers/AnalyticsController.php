<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview()
    {
        $totalRevenue  = Order::where('payment_status', 'paid')->sum('total');
        $totalExpenses = Transaction::where('type', 'expense')->sum('amount');
        $totalIncome   = Transaction::where('type', 'income')->sum('amount');

        return response()->json([
            'total_revenue'        => $totalRevenue,
            'total_expenses'       => $totalExpenses,
            'net_profit'           => $totalIncome - $totalExpenses,
            'total_orders'         => Order::count(),
            'total_customers'      => Customer::count(),
            'total_products'       => Product::count(),
            'total_employees'      => Employee::count(),
            'total_suppliers'      => Supplier::count(),
            'pending_orders'       => Order::where('status', 'pending')->count(),
            'low_stock_products'   => Product::whereColumn('stock', '<=', 'stock_alert')->count(),
            'active_employees'     => Employee::where('status', 'active')->count(),
        ]);
    }

    public function salesChart()
    {
        $months = Order::selectRaw('
                MONTH(created_at) as month,
                YEAR(created_at)  as year,
                COUNT(*)          as total_orders,
                SUM(total)        as revenue
            ')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) ASC, MONTH(created_at) ASC')
            ->limit(12)
            ->get();

        return response()->json($months);
    }

    public function expensesChart()
    {
        $months = Transaction::selectRaw('
                MONTH(date) as month,
                YEAR(date)  as year,
                SUM(CASE WHEN type = "income"  THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
            ')
            ->groupByRaw('YEAR(date), MONTH(date)')
            ->orderByRaw('YEAR(date) ASC, MONTH(date) ASC')
            ->limit(12)
            ->get();

        return response()->json($months);
    }

    public function topProducts()
    {
        $products = \App\Models\OrderItem::selectRaw('
                product_name,
                SUM(quantity) as total_sold,
                SUM(total)    as total_revenue
            ')
            ->groupBy('product_name')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        return response()->json($products);
    }

    public function topCustomers()
    {
        $customers = Order::selectRaw('
                customers.name,
                COUNT(orders.id)  as total_orders,
                SUM(orders.total) as total_spent
            ')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get();

        return response()->json($customers);
    }

    public function orderStatusChart()
    {
        $statuses = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return response()->json($statuses);
    }

    public function inventoryChart()
    {
        $categories = \App\Models\Category::withCount('products')
            ->having('products_count', '>', 0)
            ->get(['name', 'products_count']);

        return response()->json($categories);
    }
}