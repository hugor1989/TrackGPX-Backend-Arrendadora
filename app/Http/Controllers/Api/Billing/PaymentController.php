<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * Listar pagos de la empresa
     * 
     * GET /api/payments
     */
    public function index(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $payments = Payment::where('company_id', $company->id)
                ->with(['invoice', 'device', 'subscription.plan'])
                ->orderBy('paid_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $payments->items(),
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar pagos sin factura (disponibles para facturar)
     * 
     * GET /api/payments/without-invoice
     */
    public function withoutInvoice(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $payments = Payment::where('company_id', $company->id)
                ->where('status', 'completed')
                ->whereDoesntHave('invoice')
                ->with(['device', 'subscription.plan'])
                ->orderBy('paid_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payments,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalle de un pago
     * 
     * GET /api/payments/{id}
     */
    public function show(Request $request, int $id)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $payment = Payment::where('company_id', $company->id)
                ->with(['invoice', 'device', 'subscription.plan'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pago: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Obtener estadísticas de pagos
     * 
     * GET /api/payments/stats
     */
    public function stats(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $stats = [
                'total_payments' => Payment::where('company_id', $company->id)->count(),
                'total_amount' => Payment::where('company_id', $company->id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'completed_payments' => Payment::where('company_id', $company->id)
                    ->where('status', 'completed')
                    ->count(),
                'pending_payments' => Payment::where('company_id', $company->id)
                    ->where('status', 'pending')
                    ->count(),
                'failed_payments' => Payment::where('company_id', $company->id)
                    ->where('status', 'failed')
                    ->count(),
                'without_invoice' => Payment::where('company_id', $company->id)
                    ->where('status', 'completed')
                    ->whereDoesntHave('invoice')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==================== HELPERS ====================

    protected function getAuthenticatedCompany(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }

        if (method_exists($user, 'company')) {
            return $user->company;
        }

        if (method_exists($user, 'companyUser')) {
            return $user->companyUser?->company;
        }

        if (isset($user->company_id)) {
            return Company::find($user->company_id);
        }

        return null;
    }
}