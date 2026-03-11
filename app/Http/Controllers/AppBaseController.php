<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AppBaseController extends Controller
{
    /**
     * Success response
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success'     => true,
            'message'     => $message,
            'data'        => $data,
            'status_code' => $code,
        ], $code);
    }

    /**
     * Error response
     */
    protected function error(
        string $message = 'Error',
        int $code = 400,
        mixed $errors = []
    ): JsonResponse {
        return response()->json([
            'success'     => false,
            'message'     => $message,
            'errors'      => $errors,
            'status_code' => $code,
        ], $code);
    }

    /**
     * Custom response (example: login with token)
     */
    protected function respond(
        bool $success,
        ?string $token = null,
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success'     => $success,
            'token'       => $token,
            'data'        => $data,
            'message'     => $message,
            'status_code' => $statusCode
        ], $statusCode);
    }
}
