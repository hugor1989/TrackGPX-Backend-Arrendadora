<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Example usage:
     * ->middleware('role:admin')
     * ->middleware('role:admin,company')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated user'
            ], 401);
        }

        // Convertimos roles a minúsculas
        $roles = array_map('strtolower', $roles);

        // Suponiendo que tu modelo User tiene un campo "role"
        // 'admin' | 'company' | 'customer'
        $userRole = strtolower($user->role ?? '');

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: insufficient role permissions'
            ], 403);
        }

        return $next($request);
    }
}
