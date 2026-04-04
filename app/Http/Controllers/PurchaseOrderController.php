<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with('supplier', 'user');

        if ($request->search) {
            $query->where('po_number', 'like', '%' . $request->search . '%');
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
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'payment_status'       => 'required|in:unpaid,paid,partial',
            'expected_date'        => 'nullable|date',
            'tax'                  => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_cost'    => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['unit_cost'];
            }

            $tax   = $request->tax ?? 0;
            $total = $subtotal + $tax;

            $po = PurchaseOrder::create([
                'po_number'      => 'PO-' . strtoupper(uniqid()),
                'supplier_id'    => $request->supplier_id,
                'user_id'        => $request->user()->id,
                'status'         => 'draft',
                'payment_status' => $request->payment_status,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'total'          => $total,
                'expected_date'  => $request->expected_date,
                'notes'          => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'product_name'      => $product->name,
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $item['unit_cost'],
                    'total'             => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            DB::commit();

            $po->load('supplier', 'items');

            return response()->json([
                'message' => 'Purchase order created successfully',
                'order'   => $po,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create purchase order: ' . $e->getMessage()], 500);
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('supplier', 'items.product', 'user');
        return response()->json($purchaseOrder);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'status'         => 'required|in:draft,sent,received,cancelled',
            'payment_status' => 'required|in:unpaid,paid,partial',
        ]);

        if ($request->status === 'received' && $purchaseOrder->status !== 'received') {
            foreach ($purchaseOrder->items as $item) {
                if ($item->product_id) {
                    Product::find($item->product_id)?->increment('stock', $item->quantity);
                }
            }
        }

        $purchaseOrder->update($request->only('status', 'payment_status'));

        return response()->json([
            'message' => 'Purchase order updated successfully',
            'order'   => $purchaseOrder,
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();
        return response()->json(['message' => 'Purchase order deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_orders'    => PurchaseOrder::count(),
            'draft_orders'    => PurchaseOrder::where('status', 'draft')->count(),
            'received_orders' => PurchaseOrder::where('status', 'received')->count(),
            'total_spent'     => PurchaseOrder::where('payment_status', 'paid')->sum('total'),
        ]);
    }
}