<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Obtiene el usuario desde el request (correcto y sin warnings)
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        // Si es Super Admin → acceso total
        if ($user->roles()->where('name', 'super_admin')->exists()) {
            return $next($request);
        }

        // Obtener company_id del usuario autenticado
        $userCompanyId = $user->company_id;

        // Buscar company_id en la ruta
        $routeCompanyId = $request->route('companyId')
            ?? $request->route('id')
            ?? $request->company_id;

        // Si la ruta no requiere validar empresa
        if (!$routeCompanyId) {
            return $next($request);
        }

        // Comparar company_id
        if ((int) $routeCompanyId !== (int) $userCompanyId) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a esta empresa'
            ], 403);
        }

        return $next($request);
    }
}
