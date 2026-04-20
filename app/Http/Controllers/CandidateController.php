<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'job_position_id'   => 'nullable|exists:job_positions,id',
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|email|unique:candidates',
            'phone'             => 'nullable|string|max:20',
            'cover_letter'      => 'nullable|string',
            'cv'                => 'nullable|file|mimes:pdf|max:5120',
            'certificate'       => 'nullable|file|mimes:pdf|max:5120',
            'id_document'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'passport_photo'    => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'status'            => 'sometimes|string',
        ]);

        $data = [
            'job_position_id' => $request->job_position_id,
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'phone'           => $request->phone,
            'cover_letter'    => $request->cover_letter,
            'status'          => $request->status ?? 'applied',
        ];

        // CV
        if ($request->hasFile('cv')) {
            $file = $request->file('cv');
            $data['cv_path']          = $file->store('candidates/cv', 'public');
            $data['cv_original_name'] = $file->getClientOriginalName();
        }

        // Academic Certificate
        if ($request->hasFile('certificate')) {
            $file = $request->file('certificate');
            $data['certificate_path']          = $file->store('candidates/certificates', 'public');
            $data['certificate_original_name'] = $file->getClientOriginalName();
        }

        // ID Document
        if ($request->hasFile('id_document')) {
            $file = $request->file('id_document');
            $data['id_document_path']          = $file->store('candidates/id_documents', 'public');
            $data['id_document_original_name'] = $file->getClientOriginalName();
        }

        // Passport Photo
        if ($request->hasFile('passport_photo')) {
            $data['passport_photo_path'] = $request->file('passport_photo')
                ->store('candidates/passport_photos', 'public');
        }

        $candidate = Candidate::create($data);

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
        // Delete all stored files
        foreach (['cv_path', 'certificate_path', 'id_document_path', 'passport_photo_path'] as $field) {
            if ($candidate->$field) {
                Storage::disk('public')->delete($candidate->$field);
            }
        }

        $candidate->delete();
        return response()->json(['message' => 'Candidate deleted successfully']);
    }

    public function downloadFile(Candidate $candidate, string $type)
    {
        $fileMap = [
            'cv'          => ['path' => 'cv_path',          'name' => 'cv_original_name',          'label' => 'CV'],
            'certificate' => ['path' => 'certificate_path', 'name' => 'certificate_original_name', 'label' => 'Certificate'],
            'id'          => ['path' => 'id_document_path', 'name' => 'id_document_original_name', 'label' => 'ID'],
        ];

        if (!isset($fileMap[$type])) {
            return response()->json(['message' => 'Invalid file type'], 400);
        }

        $map      = $fileMap[$type];
        $filePath = $candidate->{$map['path']};

        if (!$filePath) {
            return response()->json(['message' => 'File not uploaded'], 404);
        }

        $fullPath = storage_path('app/public/' . $filePath);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File not found on server'], 404);
        }

        $candidateName = $candidate->first_name . '_' . $candidate->last_name;
        $extension     = pathinfo($fullPath, PATHINFO_EXTENSION);
        $filename      = $candidateName . '_' . $map['label'] . '.' . $extension;

        return response()->download($fullPath, $filename);
    }

    public function downloadPassportPhoto(Candidate $candidate)
    {
        if (!$candidate->passport_photo_path) {
            return response()->json(['message' => 'No passport photo uploaded'], 404);
        }

        $fullPath = storage_path('app/public/' . $candidate->passport_photo_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $filename  = $candidate->first_name . '_' . $candidate->last_name . '_Photo.' . $extension;

        return response()->download($fullPath, $filename);
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

        $existing = Employee::where('email', $candidate->email)->first();
        if ($existing) {
            return response()->json([
                'message' => 'An employee with this email already exists: ' . $candidate->email
            ], 422);
        }

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

        $existingUser = \App\Models\User::where('email', $candidate->email)->first();

        if (!$existingUser) {
            $role         = \App\Models\Role::where('name', 'employee')->first();
            $tempPassword = \Illuminate\Support\Str::random(10);

            $user = \App\Models\User::create([
                'name'     => $candidate->first_name . ' ' . $candidate->last_name,
                'email'    => $candidate->email,
                'password' => \Illuminate\Support\Facades\Hash::make($tempPassword),
                'role_id'  => $role?->id,
            ]);

            $employee->update(['user_id' => $user->id]);

            try {
                \Illuminate\Support\Facades\Mail::to($employee->email)
                    ->send(new \App\Mail\CandidateHiredMail($employee, $tempPassword));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Hire email failed: ' . $e->getMessage());
            }
        } else {
            $employee->update(['user_id' => $existingUser->id]);
        }

        $candidate->update(['status' => 'hired']);

        if ($candidate->job_position_id) {
            \App\Models\JobPosition::find($candidate->job_position_id)
                ?->update(['status' => 'closed']);
        }

        return response()->json([
            'message'  => 'Candidate hired successfully! Login credentials sent by email.',
            'employee' => $employee,
        ], 201);
    }

    public function stats()
    {
        return response()->json([
            'total_candidates'       => Candidate::count(),
            'applied_candidates'     => Candidate::where('status', 'applied')->count(),
            'reviewing_candidates'   => Candidate::where('status', 'reviewing')->count(),
            'interviewed_candidates' => Candidate::where('status', 'interviewed')->count(),
            'hired_candidates'       => Candidate::where('status', 'hired')->count(),
            'rejected_candidates'    => Candidate::where('status', 'rejected')->count(),
        ]);
    }
}