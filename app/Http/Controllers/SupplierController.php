<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $suppliers = $query->withCount('purchaseOrders')->orderBy('created_at', 'desc')->get();

        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|unique:suppliers',
            'phone'          => 'nullable|string|max:20',
            'address'        => 'nullable|string',
            'city'           => 'nullable|string',
            'country'        => 'nullable|string',
            'contact_person' => 'nullable|string',
            'status'         => 'required|in:active,inactive',
        ]);

        $supplier = Supplier::create($request->all());

        return response()->json([
            'message'  => 'Supplier created successfully',
            'supplier' => $supplier,
        ], 201);
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('purchaseOrders');
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|unique:suppliers,email,' . $supplier->id,
            'phone'          => 'nullable|string|max:20',
            'address'        => 'nullable|string',
            'city'           => 'nullable|string',
            'country'        => 'nullable|string',
            'contact_person' => 'nullable|string',
            'status'         => 'required|in:active,inactive',
        ]);

        $supplier->update($request->all());

        return response()->json([
            'message'  => 'Supplier updated successfully',
            'supplier' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(['message' => 'Supplier deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_suppliers'  => Supplier::count(),
            'active_suppliers' => Supplier::where('status', 'active')->count(),
        ]);
    }
}