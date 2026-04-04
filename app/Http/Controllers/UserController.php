<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role');

        if ($request->search) {
            $query->where('name',  'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|string|exists:roles,name',
        ]);

        $role = Role::where('name', $request->role)->first();

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role_id'  => $role->id,
        ]);

        $user->load('role');

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'required|string|exists:roles,name',
            'password' => 'nullable|string|min:8',
        ]);

        $role = Role::where('name', $request->role)->first();

        $user->update([
            'name'    => $request->name,
            'email'   => $request->email,
            'role_id' => $role->id,
            ...($request->password ? ['password' => Hash::make($request->password)] : []),
        ]);

        $user->load('role');

        return response()->json([
            'message' => 'User updated successfully',
            'user'    => $user,
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'You cannot delete yourself'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    public function stats()
    {
        return response()->json([
            'total_users'    => User::count(),
            'admin_users'    => User::whereHas('role', fn($q) => $q->where('name', 'admin'))->count(),
            'manager_users'  => User::whereHas('role', fn($q) => $q->where('name', 'manager'))->count(),
            'employee_users' => User::whereHas('role', fn($q) => $q->where('name', 'employee'))->count(),
        ]);
    }
}