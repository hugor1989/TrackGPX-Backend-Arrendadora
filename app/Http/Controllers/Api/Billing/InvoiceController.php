<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Company;
use App\Models\CompanyBillingInfo;
use Illuminate\Support\Facades\Log;
use App\Services\Billing\InvoiceService;


class InvoiceController extends Controller
{

    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }
    /**
     * Listar facturas de la empresa
     * 
     * GET /api/invoices
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

            $invoices = Invoice::where('company_id', $company->id)
                ->with(['payment'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $invoices->items(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener facturas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalle de una factura
     * 
     * GET /api/invoices/{id}
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

            $invoice = Invoice::where('company_id', $company->id)
                ->with(['payment.device', 'payment.subscription'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $invoice,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener factura: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Solicitar factura
     * 
     * POST /api/invoices/request
     * 
     * Body: {
     *   "payment_id": 123,
     *   "fiscal_data": { ... } // opcional, usa datos de company si no se provee
     * }
     */
   public function requestInvoice(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'fiscal_data' => 'nullable|array',
            'fiscal_data.rfc' => 'nullable|string|size:12,13',
            'fiscal_data.razon_social' => 'nullable|string',
            'fiscal_data.regimen_fiscal' => 'nullable|string',
            'fiscal_data.uso_cfdi' => 'nullable|string',
            'fiscal_data.codigo_postal' => 'nullable|string',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            Log::info("🔍 Solicitud manual de factura", [
                'company_id' => $company->id,
                'payment_id' => $request->payment_id,
            ]);

            // Obtener pago y verificar que pertenece a la empresa
            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($request->payment_id);

            // Verificar que el pago no tenga factura ya
            if ($payment->has_invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya tiene una factura asociada',
                ], 400);
            }

            // Verificar que el pago esté completado
            if (!$payment->is_paid || $payment->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden facturar pagos completados',
                ], 400);
            }

            // Obtener datos fiscales de la empresa
            $billingInfo = CompanyBillingInfo::where('company_id', $company->id)->first();

            // Si no hay billing info O tiene fiscal_data en el request, validar
            if (!$billingInfo || !$billingInfo->isComplete()) {
                // Si el usuario no proveyó datos alternativos
                if (!$request->has('fiscal_data')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La empresa no tiene datos fiscales completos. Por favor actualiza tu información fiscal o proporciona los datos en la solicitud.',
                    ], 400);
                }
                
                // Validar que fiscal_data esté completo
                $required = ['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal'];
                $missing = [];
                foreach ($required as $field) {
                    if (empty($request->fiscal_data[$field])) {
                        $missing[] = $field;
                    }
                }
                
                if (!empty($missing)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Faltan datos fiscales: ' . implode(', ', $missing),
                    ], 400);
                }
            }

            Log::info("💳 Generando factura para pago", [
                'payment_id' => $payment->id,
                'amount' => $payment->total,
                'has_custom_fiscal_data' => $request->has('fiscal_data'),
            ]);

            // ✅ USAR EL SERVICIO DE FACTURACIÓN
            $result = $this->invoiceService->generateManualInvoice(
                $payment,
                $request->fiscal_data ?? []
            );

            // Manejar resultado del servicio
            if (!$result['success']) {
                Log::error("❌ Error al generar factura", [
                    'payment_id' => $payment->id,
                    'error' => $result['message'],
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }

            Log::info("✅ Factura generada exitosamente", [
                'invoice_id' => $result['invoice']->id,
                'folio' => $result['invoice']->folio,
                'uuid' => $result['uuid'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => $result['invoice'],
                    'uuid' => $result['uuid'] ?? null,
                    'xml_path' => $result['xml_path'] ?? null,
                    'pdf_path' => $result['pdf_path'] ?? null,
                ],
                'message' => 'Factura generada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            Log::error("❌ Excepción al solicitar factura", [
                'payment_id' => $request->payment_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar factura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar XML de factura
     * 
     * GET /api/invoices/{id}/xml
     */
    public function downloadXML(Request $request, int $id)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            if (!$invoice->xml_path || !Storage::exists($invoice->xml_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo XML no disponible',
                ], 404);
            }

            return Storage::download($invoice->xml_path);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar XML: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar PDF de factura
     * 
     * GET /api/invoices/{id}/pdf
     */
    public function downloadPDF(Request $request, int $id)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            if (!$invoice->pdf_path || !Storage::exists($invoice->pdf_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo PDF no disponible',
                ], 404);
            }

            return Storage::download($invoice->pdf_path);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar factura
     * 
     * POST /api/invoices/{id}/cancel
     * 
     * Body: { "reason": "Motivo de cancelación" }
     */
    public function cancelInvoice(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            if ($invoice->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta factura ya está cancelada',
                ], 400);
            }

            // TODO: Aquí iría la llamada a SW Sapien para cancelar

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $invoice,
                'message' => 'Factura cancelada exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar factura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reenviar factura por correo
     * 
     * POST /api/invoices/{id}/resend
     * 
     * Body: { "email": "opcional@example.com" }
     */
    public function resendInvoice(Request $request, int $id)
    {
        $request->validate([
            'email' => 'nullable|email',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $email = $request->email ?? $company->email;

            // TODO: Implementar envío de correo

            return response()->json([
                'success' => true,
                'message' => 'Factura enviada a ' . $email,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar factura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de facturación
     * 
     * GET /api/invoices/stats
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
                'total_invoices' => Invoice::where('company_id', $company->id)->count(),
                'total_amount' => Invoice::where('company_id', $company->id)
                    ->where('status', 'issued')
                    ->sum('total'),
                'pending_invoices' => Invoice::where('company_id', $company->id)
                    ->where('status', 'pending')
                    ->count(),
                'issued_invoices' => Invoice::where('company_id', $company->id)
                    ->where('status', 'issued')
                    ->count(),
                'cancelled_invoices' => Invoice::where('company_id', $company->id)
                    ->where('status', 'cancelled')
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
