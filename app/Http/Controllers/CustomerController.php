<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $customers = $query->withCount('orders')->orderBy('created_at', 'desc')->get();

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|unique:customers',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city'    => 'nullable|string',
            'country' => 'nullable|string',
            'status'  => 'required|in:active,inactive',
        ]);

        $customer = Customer::create($request->all());

        return response()->json([
            'message'  => 'Customer created successfully',
            'customer' => $customer,
        ], 201);
    }

    public function show(Customer $customer)
    {
        $customer->load('orders');
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city'    => 'nullable|string',
            'country' => 'nullable|string',
            'status'  => 'required|in:active,inactive',
        ]);

        $customer->update($request->all());

        return response()->json([
            'message'  => 'Customer updated successfully',
            'customer' => $customer,
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_customers'  => Customer::count(),
            'active_customers' => Customer::where('status', 'active')->count(),
        ]);
    }
}