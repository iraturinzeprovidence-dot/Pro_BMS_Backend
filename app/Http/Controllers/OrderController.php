<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('customer', 'user');

        if ($request->search) {
            $query->where('order_number', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'payment_method'       => 'required|in:cash,bank_transfer,card,other',
            'payment_status'       => 'required|in:unpaid,paid,partial',
            'tax'                  => 'nullable|numeric|min:0',
            'discount'             => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_price'   => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $tax      = $request->tax      ?? 0;
            $discount = $request->discount ?? 0;
            $total    = $subtotal + $tax - $discount;

            $order = Order::create([
                'order_number'   => 'ORD-' . strtoupper(uniqid()),
                'customer_id'    => $request->customer_id,
                'user_id'        => $request->user()->id,
                'status'         => 'pending',
                'payment_status' => $request->payment_status,
                'payment_method' => $request->payment_method,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'discount'       => $discount,
                'total'          => $total,
                'notes'          => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $product->name,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'total'        => $item['quantity'] * $item['unit_price'],
                ]);

                $product->decrement('stock', $item['quantity']);
            }

            DB::commit();
            if ($order->customer?->email) {
    Mail::to($order->customer->email)->send(new OrderConfirmationMail($order));
}

            $order->load('customer', 'items');

            return response()->json([
                'message' => 'Order created successfully',
                'order'   => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    public function show(Order $order)
    {
        $order->load('customer', 'items.product', 'user');
        return response()->json($order);
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status'         => 'required|in:pending,processing,completed,cancelled',
            'payment_status' => 'required|in:unpaid,paid,partial',
        ]);

        $order->update($request->only('status', 'payment_status'));

        return response()->json([
            'message' => 'Order updated successfully',
            'order'   => $order,
        ]);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_orders'     => Order::count(),
            'pending_orders'   => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'total_revenue'    => Order::where('payment_status', 'paid')->sum('total'),
        ]);
    }
}