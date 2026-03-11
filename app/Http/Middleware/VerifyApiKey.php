<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');

        // Verificar API key
        if (!$apiKey || $apiKey !== config('app.server_api_key')) {
            return response()->json([
                'success' => false,
                'message' => 'API key inválida o no proporcionada',
            ], 401);
        }

        return $next($request);
    }
}