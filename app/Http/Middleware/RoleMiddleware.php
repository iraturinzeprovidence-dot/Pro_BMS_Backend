<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Employee;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userRole = $user->role?->name;

        // Admins always pass
        if ($userRole === 'admin') {
            return $next($request);
        }

        // Check if user role is directly in allowed roles
        if (in_array($userRole, $roles)) {

            // For managers — block purchasing and accounting
            if ($userRole === 'manager') {
                $path     = $request->path();
                $segments = explode('/', $path);
                $module   = $segments[1] ?? null;

                $managerBlocked = ['purchasing', 'accounting'];
                if (in_array($module, $managerBlocked)) {
                    return response()->json([
                        'message' => 'Access denied. Managers cannot access this module.'
                    ], 403);
                }
            }

            return $next($request);
        }

        // For employees — check their linked employee permissions
        if ($userRole === 'employee') {
            $employee = Employee::where('email', $user->email)->first();

            if ($employee && !empty($employee->permissions)) {
                $path     = $request->path();
                $segments = explode('/', $path);
                $module   = $segments[1] ?? null;

                // Direct module match
                if ($module && in_array($module, $employee->permissions)) {
                    return $next($request);
                }

                // PDF exports — check sub-path
                if ($module === 'pdf') {
                    $subPath    = $segments[2] ?? '';
                    $pdfPermMap = [
                        'order'        => 'sales',
                        'transactions' => 'accounting',
                        'employees'    => 'hr',
                    ];
                    foreach ($pdfPermMap as $key => $perm) {
                        if (str_starts_with($subPath, $key)) {
                            if (in_array($perm, $employee->permissions)) {
                                return $next($request);
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Access denied. You do not have permission to access this module.'
        ], 403);
    }
}