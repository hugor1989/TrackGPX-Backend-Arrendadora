<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    protected PaymentProcessingService $paymentService;

    public function __construct(PaymentProcessingService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Listar todos los métodos de pago de la empresa
     * 
     * GET /api/billing/payment-methods
     */
    public function index(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return $this->errorResponse('No se encontró la empresa del usuario', 404);
            }

            $result = $this->paymentService->listPaymentMethods($company);

            if ($result['success']) {
                return $this->successResponse([
                    'cards' => $result['cards'],
                ], 'Métodos de pago obtenidos correctamente');
            }

            return $this->errorResponse($result['error'], 500);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener métodos de pago: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Agregar un nuevo método de pago (tarjeta)
     * 
     * POST /api/billing/payment-methods
     * 
     * Body:
     * {
     *   "token_id": "k9pn8qtsvr7k8gld3r1m",
     *   "device_session_id": "abc123xyz"
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'token_id' => 'required|string',
            'device_session_id' => 'nullable|string'
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return $this->errorResponse('No se encontró la empresa del usuario', 404);
            }

            if (!$company->openpay_customer_id) {
                return $this->errorResponse('La empresa no tiene un customer_id de OpenPay', 400);
            }

            $result = $this->paymentService->addPaymentMethod(
                $company,
                $request->token_id,
                $request->device_session_id
            );

            if ($result['success']) {
                return $this->successResponse([
                    'card' => $result['card'],
                ], 'Tarjeta agregada exitosamente', 201);
            }

            return $this->errorResponse($result['error'], 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar método de pago: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener un método de pago específico
     * 
     * GET /api/billing/payment-methods/{cardId}
     */
    public function show(Request $request, string $cardId)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company || !$company->openpay_customer_id) {
                return $this->errorResponse('Empresa no encontrada o sin customer_id', 404);
            }

            // ✅ Usar el método del servicio
            $result = $this->paymentService->getPaymentMethod($company, $cardId);

            if ($result['success']) {
                return $this->successResponse([
                    'card' => $result['card'],
                ], 'Método de pago obtenido correctamente');
            }

            return $this->errorResponse($result['error'], 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener método de pago: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar un método de pago
     * 
     * DELETE /api/billing/payment-methods/{cardId}
     */
    public function destroy(Request $request, string $cardId)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company || !$company->openpay_customer_id) {
                return $this->errorResponse('Empresa no encontrada o sin customer_id', 404);
            }

            $result = $this->paymentService->deletePaymentMethod($company, $cardId);

            if ($result['success']) {
                return $this->successResponse(null, 'Método de pago eliminado correctamente');
            }

            return $this->errorResponse($result['error'], 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar método de pago: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener la configuración pública de OpenPay (para el frontend)
     * 
     * GET /api/billing/payment-methods/config
     */
    public function config(Request $request)
    {
        return $this->successResponse([
            'merchant_id' => config('openpay.merchant_id'),
            'public_key' => $this->paymentService->getPublicKey(),
            'is_sandbox' => $this->paymentService->isSandbox(),
        ], 'Configuración obtenida correctamente');
    }

    // ==================== HELPERS ====================

    /**
     * Obtener la empresa autenticada del usuario
     */
    protected function getAuthenticatedCompany(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // Opción 1: Si el usuario tiene relación directa con company
        if (method_exists($user, 'company')) {
            return $user->company;
        }

        // Opción 2: Si usas CompanyUser
        if (method_exists($user, 'companyUser')) {
            return $user->companyUser?->company;
        }

        // Opción 3: Si el company_id está directo en el usuario
        if (isset($user->company_id)) {
            return \App\Models\Company::find($user->company_id);
        }

        return null;
    }

    /**
     * Respuesta exitosa estandarizada
     */
    protected function successResponse($data = null, string $message = 'Operación exitosa', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Respuesta de error estandarizada
     */
    protected function errorResponse(string $message, int $status = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
