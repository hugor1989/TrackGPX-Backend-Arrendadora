<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        // Si NO es un request JSON → deja a Laravel actuar normal
        if (! $request->expectsJson()) {
            return parent::render($request, $exception);
        }

        // 404
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Ruta no encontrada',
            ], 404);
        }

        // 405
        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Método HTTP no permitido',
            ], 405);
        }

        // 401
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        // 422 – validación
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $exception->errors(),
            ], 422);
        }

        // 409 – duplicados SQL
        if ($exception instanceof UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'message' => 'El valor enviado ya existe (conflicto de duplicado)',
            ], 409);
        }

        // Errores desconocidos — mostrar mensaje claro
        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor',
            'error' => env('APP_DEBUG') ? $exception->getMessage() : null,
        ], 500);
    }
}
