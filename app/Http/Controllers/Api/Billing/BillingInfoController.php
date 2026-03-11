<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\CompanyBillingInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingInfoController extends Controller
{
    /**
     * Obtener información de facturación de la empresa
     * 
     * GET /api/billing/billing-info
     */
    public function index(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return $this->errorResponse('No se encontró la empresa del usuario', 404);
            }

            $billingInfo = CompanyBillingInfo::where('company_id', $company->id)
                ->first();

            if (!$billingInfo) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay información de facturación configurada',
                    'data' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $billingInfo,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información de facturación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear o actualizar información de facturación
     * 
     * POST /api/billing/billing-info
     * 
     * Body:
     * {
     *   "rfc": "XAXX010101000",
     *   "legal_name": "Empresa SA de CV",
     *   "fiscal_regime": "601",
     *   "tax_regime": "Persona Moral",
     *   "zip_code": "12345",
     *   "email": "facturacion@empresa.com",
     *   "phone": "5512345678",
     *   "street": "Calle Principal",
     *   "exterior_number": "123",
     *   "interior_number": "A",
     *   "neighborhood": "Centro",
     *   "city": "CDMX",
     *   "state": "Ciudad de México",
     *   "country": "México",
     *   "cfdi_use": "G03"
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'rfc' => 'required|string|size:13',
            'legal_name' => 'required|string|max:255',
            'fiscal_regime' => 'required|string|max:10',
            'tax_regime' => 'nullable|string|max:100',
            'postal_code' => 'required|string|size:5',
            'email_for_invoices' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'cfdi_use' => 'required|string|max:10',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return $this->errorResponse('No se encontró la empresa del usuario', 404);
            }

            // Desactivar información anterior
           /*  CompanyBillingInfo::where('company_id', $company->id)
                ->update(['is_active' => false]); */

            // Crear nueva información
            $billingInfo = CompanyBillingInfo::create(array_merge(
                $request->all(),
                [
                    'company_id' => $company->id,
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Información de facturación guardada exitosamente',
                'data' => $billingInfo,
            ], 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al guardar información de facturación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar información de facturación existente
     * 
     * PUT /api/billing/billing-info/{id}
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'rfc' => 'sometimes|required|string|size:13',
            'legal_name' => 'sometimes|required|string|max:255',
            'fiscal_regime' => 'sometimes|required|string|max:10',
            'tax_regime' => 'nullable|string|max:100',
            'postal_code' => 'sometimes|required|string|size:5',
            'email_for_invoices' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'cfdi_use' => 'sometimes|required|string|max:10',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            $billingInfo = CompanyBillingInfo::where('id', $id)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $billingInfo->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Información de facturación actualizada exitosamente',
                'data' => $billingInfo->fresh(),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar información de facturación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verificar si la información de facturación está completa
     * 
     * GET /api/billing/billing-info/validate
     */
    public function validate(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            $billingInfo = CompanyBillingInfo::where('company_id', $company->id)
                ->first();

            if (!$billingInfo) {
                return response()->json([
                    'success' => true,
                    'is_complete' => false,
                    'message' => 'No hay información de facturación configurada',
                ]);
            }

            $isComplete = $billingInfo->isComplete();

            return response()->json([
                'success' => true,
                'is_complete' => $isComplete,
                'message' => $isComplete 
                    ? 'La información de facturación está completa' 
                    : 'La información de facturación está incompleta',
                'data' => $billingInfo,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al validar información: ' . $e->getMessage(), 500);
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
            return \App\Models\Company::find($user->company_id);
        }

        return null;
    }

    protected function errorResponse(string $message, int $status = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}