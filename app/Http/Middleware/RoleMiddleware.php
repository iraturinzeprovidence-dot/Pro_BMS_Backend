<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userRole = $user->role?->name;

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Access denied. You do not have permission.'
            ], 403);
        }

        return $next($request);
    }
}