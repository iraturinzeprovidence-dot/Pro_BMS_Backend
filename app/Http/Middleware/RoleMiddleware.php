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

        // Check if user's role is in allowed roles
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        // For employees — check their module permissions
        if ($userRole === 'employee') {
            $employee = Employee::where('email', $user->email)->first();

            if ($employee && $employee->permissions) {
                // Map route prefixes to permission keys
                $routePermissionMap = [
                    'inventory'  => 'inventory',
                    'sales'      => 'sales',
                    'purchasing' => 'purchasing',
                    'hr'         => 'hr',
                    'accounting' => 'accounting',
                    'analytics'  => 'analytics',
                    'pdf'        => null, // handled separately below
                ];

                $path = $request->path();

                // Extract module from path e.g. api/inventory/products → inventory
                $segments   = explode('/', $path);
                $moduleKey  = $segments[1] ?? null; // api/MODULE/...

                if ($moduleKey && isset($routePermissionMap[$moduleKey])) {
                    $requiredPermission = $routePermissionMap[$moduleKey];

                    // If permission is null it means open to all authenticated
                    if ($requiredPermission === null) {
                        return $next($request);
                    }

                    if (in_array($requiredPermission, $employee->permissions)) {
                        return $next($request);
                    }
                }

                // PDF exports — check based on pdf sub-path
                if ($moduleKey === 'pdf') {
                    $subPath = $segments[2] ?? '';
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