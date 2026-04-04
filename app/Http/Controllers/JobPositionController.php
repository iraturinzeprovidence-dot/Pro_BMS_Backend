<?php

namespace App\Http\Controllers;

use App\Models\JobPosition;
use Illuminate\Http\Request;

class JobPositionController extends Controller
{
    public function index(Request $request)
    {
        $query = JobPosition::withCount('candidates');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where('title',      'like', '%' . $request->search . '%')
                  ->orWhere('department', 'like', '%' . $request->search . '%');
        }

        $jobs = $query->orderBy('created_at', 'desc')->get();

        return response()->json($jobs);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'department'   => 'required|string|max:255',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'type'         => 'required|in:full_time,part_time,contract,internship',
            'status'       => 'required|in:open,closed',
            'salary_min'   => 'nullable|numeric|min:0',
            'salary_max'   => 'nullable|numeric|min:0',
            'deadline'     => 'nullable|date',
        ]);

        $job = JobPosition::create($request->all());

        return response()->json([
            'message' => 'Job position created successfully',
            'job'     => $job,
        ], 201);
    }

    public function show(JobPosition $jobPosition)
    {
        $jobPosition->load('candidates');
        return response()->json($jobPosition);
    }

    public function update(Request $request, JobPosition $jobPosition)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'department'   => 'required|string|max:255',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'type'         => 'required|in:full_time,part_time,contract,internship',
            'status'       => 'required|in:open,closed',
            'salary_min'   => 'nullable|numeric|min:0',
            'salary_max'   => 'nullable|numeric|min:0',
            'deadline'     => 'nullable|date',
        ]);

        $jobPosition->update($request->all());

        return response()->json([
            'message' => 'Job position updated successfully',
            'job'     => $jobPosition,
        ]);
    }

    public function destroy(JobPosition $jobPosition)
    {
        $jobPosition->delete();
        return response()->json(['message' => 'Job position deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_jobs'  => JobPosition::count(),
            'open_jobs'   => JobPosition::where('status', 'open')->count(),
            'closed_jobs' => JobPosition::where('status', 'closed')->count(),
        ]);
    }
}