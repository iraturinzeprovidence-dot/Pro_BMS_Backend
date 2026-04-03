<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|unique:products',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'stock_alert' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'status'      => 'required|in:active,inactive',
        ]);

        $product = Product::create($request->all());
        $product->load('category');

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    public function show(Product $product)
    {
        $product->load('category');
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|unique:products,sku,' . $product->id,
            'price'       => 'required|numeric|min:0',
            'cost'        => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'stock_alert' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'status'      => 'required|in:active,inactive',
        ]);

        $product->update($request->all());
        $product->load('category');

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product,
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function lowStock()
    {
        $products = Product::with('category')
            ->whereColumn('stock', '<=', 'stock_alert')
            ->where('status', 'active')
            ->get();

        return response()->json($products);
    }

    public function stats()
    {
        return response()->json([
            'total_products'   => Product::count(),
            'active_products'  => Product::where('status', 'active')->count(),
            'low_stock'        => Product::whereColumn('stock', '<=', 'stock_alert')->count(),
            'total_categories' => \App\Models\Category::count(),
        ]);
    }
}