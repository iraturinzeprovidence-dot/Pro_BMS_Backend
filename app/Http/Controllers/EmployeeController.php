<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();

        if ($request->search) {
            $query->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name',  'like', '%' . $request->search . '%')
                  ->orWhere('email',      'like', '%' . $request->search . '%')
                  ->orWhere('department', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->department) {
            $query->where('department', $request->department);
        }

        $employees = $query->orderBy('created_at', 'desc')->get();

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:employees',
            'phone'           => 'nullable|string|max:20',
            'department'      => 'required|string|max:255',
            'job_title'       => 'required|string|max:255',
            'salary'          => 'required|numeric|min:0',
            'hire_date'       => 'required|date',
            'status'          => 'required|in:active,inactive,terminated',
            'address'         => 'nullable|string',
        ]);

        $employee = Employee::create([
            'employee_number' => 'EMP-' . strtoupper(uniqid()),
            ...$request->all(),
        ]);

        return response()->json([
            'message'  => 'Employee created successfully',
            'employee' => $employee,
        ], 201);
    }

    public function show(Employee $employee)
    {
        return response()->json($employee);
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'required|email|unique:employees,email,' . $employee->id,
            'phone'       => 'nullable|string|max:20',
            'department'  => 'required|string|max:255',
            'job_title'   => 'required|string|max:255',
            'salary'      => 'required|numeric|min:0',
            'hire_date'   => 'required|date',
            'status'      => 'required|in:active,inactive,terminated',
            'address'     => 'nullable|string',
        ]);

        $employee->update($request->all());

        return response()->json([
            'message'  => 'Employee updated successfully',
            'employee' => $employee,
        ]);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_employees'      => Employee::count(),
            'active_employees'     => Employee::where('status', 'active')->count(),
            'terminated_employees' => Employee::where('status', 'terminated')->count(),
            'departments'          => Employee::distinct()->pluck('department')->count(),
        ]);
    }

    public function departments()
    {
        $departments = Employee::distinct()->pluck('department');
        return response()->json($departments);
    }
}