<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Mail\CandidateHiredMail;
use Illuminate\Support\Facades\Mail;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $query = Candidate::with('jobPosition');

        if ($request->search) {
            $query->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name',  'like', '%' . $request->search . '%')
                  ->orWhere('email',      'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $candidates = $query->orderBy('created_at', 'desc')->get();

        return response()->json($candidates);
    }

    public function store(Request $request)
    {
        $request->validate([
            'job_position_id' => 'nullable|exists:job_positions,id',
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:candidates',
            'phone'           => 'nullable|string|max:20',
            'cover_letter'    => 'nullable|string',
            'status'          => 'required|in:applied,reviewing,interviewed,hired,rejected',
        ]);

        $candidate = Candidate::create($request->all());

        return response()->json([
            'message'   => 'Candidate created successfully',
            'candidate' => $candidate,
        ], 201);
    }

    public function show(Candidate $candidate)
    {
        $candidate->load('jobPosition');
        return response()->json($candidate);
    }

    public function update(Request $request, Candidate $candidate)
    {
        $request->validate([
            'status' => 'required|in:applied,reviewing,interviewed,hired,rejected',
        ]);

        $candidate->update($request->only('status'));

        return response()->json([
            'message'   => 'Candidate updated successfully',
            'candidate' => $candidate,
        ]);
    }

    public function destroy(Candidate $candidate)
    {
        $candidate->delete();
        return response()->json(['message' => 'Candidate deleted successfully']);
    }

public function hire(Candidate $candidate)
{
    $request = request();

    $request->validate([
        'department'  => 'required|string',
        'job_title'   => 'required|string',
        'salary'      => 'required|numeric|min:0',
        'hire_date'   => 'required|date',
        'permissions' => 'nullable|array',
    ]);

    // Check if employee with same email already exists
    $existing = Employee::where('email', $candidate->email)->first();
    if ($existing) {
        return response()->json([
            'message' => 'An employee with this email already exists: ' . $candidate->email
        ], 422);
    }

    // Create employee record
    $employee = Employee::create([
        'employee_number' => 'EMP-' . strtoupper(uniqid()),
        'first_name'      => $candidate->first_name,
        'last_name'       => $candidate->last_name,
        'email'           => $candidate->email,
        'phone'           => $candidate->phone,
        'department'      => $request->department,
        'job_title'       => $request->job_title,
        'salary'          => $request->salary,
        'hire_date'       => $request->hire_date,
        'status'          => 'active',
        'permissions'     => $request->permissions ?? [],
    ]);

    // Auto-create user account for the employee
    $existingUser = \App\Models\User::where('email', $candidate->email)->first();

    if (!$existingUser) {
        $role          = \App\Models\Role::where('name', 'employee')->first();
        $tempPassword  = \Illuminate\Support\Str::random(10);

        $user = \App\Models\User::create([
            'name'     => $candidate->first_name . ' ' . $candidate->last_name,
            'email'    => $candidate->email,
            'password' => \Illuminate\Support\Facades\Hash::make($tempPassword),
            'role_id'  => $role?->id,
        ]);

        // Link user to employee
        $employee->update(['user_id' => $user->id]);

        // Send welcome email with credentials
        try {
            Mail::to($employee->email)->send(
                new \App\Mail\CandidateHiredMail($employee, $tempPassword)
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send hire email: ' . $e->getMessage());
        }
    } else {
        $employee->update(['user_id' => $existingUser->id]);
    }

    // Update candidate status
    $candidate->update(['status' => 'hired']);

    // Close job position
    if ($candidate->job_position_id) {
        \App\Models\JobPosition::find($candidate->job_position_id)
            ?->update(['status' => 'closed']);
    }

    return response()->json([
        'message'  => 'Candidate hired, employee created and login credentials sent by email!',
        'employee' => $employee,
    ], 201);
}

public function stats()
{
    return response()->json([
        'total_candidates'        => Candidate::count(),
        'applied_candidates'      => Candidate::where('status', 'applied')->count(),
        'reviewing_candidates'    => Candidate::where('status', 'reviewing')->count(),
        'interviewed_candidates'  => Candidate::where('status', 'interviewed')->count(),
        'hired_candidates'        => Candidate::where('status', 'hired')->count(),
        'rejected_candidates'     => Candidate::where('status', 'rejected')->count(),
    ]);
}
}