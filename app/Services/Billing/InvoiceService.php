<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\CompanyBillingInfo;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\DeviceSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\FolioGenerator;

class InvoiceService
{
    protected array $emisor;
    protected string $baseUrl;
    protected string $apiUrl;
    protected string $token;
    protected FolioGenerator $folioGenerator;

    public function __construct(FolioGenerator $folioGenerator)
    {
        $this->folioGenerator = $folioGenerator;
        $this->baseUrl = config('sw.url', 'https://services.test.sw.com.mx');
        $this->apiUrl = str_replace('services', 'api', $this->baseUrl);
        $this->token = config('sw.token');
        
        // Datos del emisor (tu empresa)
        $this->emisor = [
            'Rfc' => config('sw.emisor_rfc'),
            'Nombre' => config('sw.emisor_nombre'),
            'RegimenFiscal' => config('sw.emisor_regimen', '601'),
        ];
    }

    /**
     * Configura los datos del emisor
     */
    public function setEmisor(array $emisor): self
    {
        $this->emisor = $emisor;
        return $this;
    }

    // ==================== MÉTODOS PRINCIPALES ====================

    /**
     * Generar factura completa para un pago
     * Este es el método principal que se usa en activación y renovación
     */
    public function generateInvoiceForPayment(Payment $payment, ?array $fiscalDataOverride = null): array
    {
        try {
            DB::beginTransaction();

            $company = $payment->company;
            $billingInfo = $fiscalDataOverride 
                ? $this->buildBillingInfoFromArray($fiscalDataOverride)
                : $company->billingInfo;

            if (!$billingInfo || !$billingInfo->isComplete()) {
                return [
                    'success' => false,
                    'message' => 'Datos fiscales incompletos',
                ];
            }

            // 1. Crear registro de factura en DB
            $invoice = $this->createInvoiceRecord($company, $payment, $billingInfo);

            // 2. Crear items de la factura
            $this->createInvoiceItems($invoice, $payment);

            // 3. Calcular totales
            $invoice->calculateTotals();
            $invoice->save();

            // 4. Timbrar con SW Sapien
            $stampResult = $this->stampInvoice($invoice, $billingInfo);

            if (!$stampResult['success']) {
                DB::rollBack();
                return $stampResult;
            }

            // 5. Actualizar factura con datos del timbrado
            $invoice->markAsIssued($stampResult['cfdi_data']);

            // 6. Generar y guardar PDF
            $pdfResult = $this->generateAndSavePdfForInvoice($invoice, $stampResult['xml']);

            // 7. Vincular pago con factura
            $payment->attachInvoice($invoice);

            DB::commit();

            return [
                'success' => true,
                'invoice' => $invoice->fresh(),
                'uuid' => $invoice->cfdi_uuid,
                'xml_path' => $invoice->cfdi_xml_path,
                'pdf_path' => $invoice->cfdi_pdf_path,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generando factura', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al generar factura: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generar factura para activación de dispositivo
     */
    public function generateActivationInvoice(
        Payment $payment,
        DeviceSubscription $subscription
    ): array {
        $payment->update([
            'description' => "Activación GPS - Plan {$subscription->plan->name} - IMEI {$subscription->device->imei}",
        ]);

        return $this->generateInvoiceForPayment($payment);
    }

    /**
     * Generar factura para renovación
     */
    public function generateRenewalInvoice(
        Payment $payment,
        DeviceSubscription $subscription
    ): array {
        $cycle = $subscription->billing_cycle === 'annual' ? 'Anual' : 'Mensual';
        $payment->update([
            'description' => "Renovación {$cycle} GPS - Plan {$subscription->plan->name} - IMEI {$subscription->device->imei}",
        ]);

        return $this->generateInvoiceForPayment($payment);
    }

    /**
     * Generar factura manual (solicitud del usuario)
     */
    public function generateManualInvoice(Payment $payment, array $fiscalData): array
    {
        // Validar que el pago no tenga factura
        if ($payment->has_invoice) {
            return [
                'success' => false,
                'message' => 'Este pago ya tiene una factura asociada',
            ];
        }

        return $this->generateInvoiceForPayment($payment, $fiscalData);
    }

    // ==================== MÉTODOS DE CREACIÓN DE REGISTROS ====================

    /**
     * Crear registro de factura en base de datos
     */
    protected function createInvoiceRecord(
        Company $company,
        Payment $payment,
        CompanyBillingInfo $billingInfo
    ): Invoice {
        // ✅ Generar folios usando FolioGenerator
        $folio = $this->folioGenerator->next();  // TGX0000001
        $invoiceNumber = $this->folioGenerator->nextInvoiceNumber(); // INV-20251209-0001

        Log::info("📄 Folios generados", [
            'folio' => $folio,
            'invoice_number' => $invoiceNumber,
        ]);

        return Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => $payment->amount,
            'tax' => $payment->tax,
            'total' => $payment->total,
            'currency' => 'MXN',
            'status' => 'pending',
            
            // ✅ FOLIO CFDI (consecutivo perpetuo)
            'folio' => $folio,
            'serie' => 'A',
            
            // Datos del emisor
            'issuer_rfc' => $this->emisor['Rfc'],
            'issuer_name' => $this->emisor['Nombre'],
            'issuer_fiscal_regime' => $this->emisor['RegimenFiscal'],
            
            // Datos del receptor
            'receiver_rfc' => $billingInfo->rfc,
            'receiver_name' => $billingInfo->legal_name,
            'receiver_fiscal_regime' => $billingInfo->fiscal_regime,
            'receiver_zip_code' => $billingInfo->postal_code,
            'receiver_tax_regime' => $billingInfo->tax_regime,
            
            // CFDI
            'cfdi_use' => $billingInfo->cfdi_use ?? 'G03',
            'cfdi_payment_method' => $billingInfo->payment_form ?? 'PUE',
            'cfdi_payment_form' => $billingInfo->payment_method ?? '03',
            'export_type' => '01',
            
            // PAC
            'pac_name' => 'SW sapien',
            'pac_rfc' => 'SPR190613I52',
        ]);
    }

    /**
     * Crear items de la factura
     */
    protected function createInvoiceItems(Invoice $invoice, Payment $payment): void
    {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'device_subscription_id' => $payment->device_subscription_id,
            'item_type' => $payment->type === 'activation' ? 'service' : 'subscription',
            'sat_product_code' => '81161700', // Servicios de rastreo
            'sat_unit_code' => 'E48', // Unidad de servicio
            'description' => $payment->description,
            'quantity' => 1,
            'unit_price' => $payment->amount,
            'subtotal' => $payment->amount,
            'discount' => 0,
            'tax_rate' => 16.00,
            'tax_amount' => $payment->tax,
            'total' => $payment->total,
        ]);
    }

    // ==================== MÉTODOS DE TIMBRADO SW SAPIEN ====================

    /**
     * Timbrar factura con SW Sapien
     */
    protected function stampInvoice(Invoice $invoice, CompanyBillingInfo $billingInfo): array
    {
        $cfdiData = $this->buildCfdiJson($invoice, $billingInfo);

        Log::error('dasts enviados', [
                'payment_id' => $cfdiData,
                'error' => $billingInfo,
            ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/jsontoxml',
        ])->post($this->baseUrl . '/v3/cfdi33/issue/json/v4', $cfdiData);

        $data = $response->json();

        if ($response->successful() && isset($data['status']) && $data['status'] === 'success') {
            $stampData = $data['data'];
            
            // Guardar XML
            $xmlPath = $this->saveXml($stampData['cfdi'], $stampData['uuid']);

            return [
                'success' => true,
                'xml' => $stampData['cfdi'],
                'cfdi_data' => [
                    'cfdi_uuid' => $stampData['uuid'],
                    'cfdi_folio' => $invoice->invoice_number,
                    'cfdi_serie' => 'TGX',
                    'cfdi_xml_path' => $xmlPath,
                    'cfdi_original_string' => $stampData['cadenaOriginalSAT'] ?? null,
                    'cfdi_sat_seal' => $stampData['selloSAT'] ?? null,
                    'cfdi_cfdi_seal' => $stampData['selloCFDI'] ?? null,
                    'cfdi_sat_cert_number' => $stampData['noCertificadoSAT'] ?? null,
                    'cfdi_stamp_date' => $stampData['fechaTimbrado'] ?? now(),
                ],
            ];
        }

        Log::error('Error timbrado SW Sapien', [
            'invoice_id' => $invoice->id,
            'response' => $data,
        ]);

        return [
            'success' => false,
            'message' => $data['message'] ?? 'Error al timbrar',
            'messageDetail' => $data['messageDetail'] ?? null,
        ];
    }

    /**
     * Construir JSON para CFDI 4.0
     */
    protected function buildCfdiJson(Invoice $invoice, CompanyBillingInfo $billingInfo): array
    {
        $item = $invoice->items->first();

        return [
            'Version' => '4.0',
            'Serie' => $invoice->serie ?? 'A',  // ✅ Usar serie de la BD
            'Folio' => $invoice->folio,          // ✅ Usar folio consecutivo (TGX0000001)
            'Fecha' => Carbon::now()->format('Y-m-d\TH:i:s'),
            'FormaPago' => $invoice->cfdi_payment_form,
            'SubTotal' => number_format($invoice->subtotal, 2, '.', ''),
            'Moneda' => $invoice->currency,
            'Total' => number_format($invoice->total, 2, '.', ''),
            'TipoDeComprobante' => 'I',
            'Exportacion' => '01',
            'MetodoPago' => $invoice->cfdi_payment_method,
            'LugarExpedicion' => config('sw.emisor_cp', '45019'),
            'Emisor' => $this->emisor,
            'Receptor' => [
                'Rfc' => $billingInfo->rfc,
                'Nombre' => $billingInfo->legal_name,
                'DomicilioFiscalReceptor' => $billingInfo->postal_code,
                'RegimenFiscalReceptor' => $billingInfo->fiscal_regime,
                'UsoCFDI' => $billingInfo->cfdi_use ?? 'G03',
            ],
            'Conceptos' => [
                [
                    'ClaveProdServ' => $item->sat_product_code,
                    'Cantidad' => $item->quantity,
                    'ClaveUnidad' => $item->sat_unit_code,
                    'Unidad' => 'Servicio',
                    'Descripcion' => $item->description,
                    'ValorUnitario' => number_format($item->unit_price, 2, '.', ''),
                    'Importe' => number_format($item->subtotal, 2, '.', ''),
                    'ObjetoImp' => '02',
                    'Impuestos' => [
                        'Traslados' => [
                            [
                                'Base' => number_format($item->subtotal, 2, '.', ''),
                                'Impuesto' => '002',
                                'TipoFactor' => 'Tasa',
                                'TasaOCuota' => '0.160000',
                                'Importe' => number_format($item->tax_amount, 2, '.', ''),
                            ]
                        ]
                    ]
                ]
            ],
            'Impuestos' => [
                'TotalImpuestosTrasladados' => number_format($invoice->tax, 2, '.', ''),
                'Traslados' => [
                    [
                        'Base' => number_format($invoice->subtotal, 2, '.', ''),
                        'Impuesto' => '002',
                        'TipoFactor' => 'Tasa',
                        'TasaOCuota' => '0.160000',
                        'Importe' => number_format($invoice->tax, 2, '.', ''),
                    ]
                ]
            ]
        ];
    }

    // ==================== MÉTODOS DE ARCHIVOS ====================

    /**
     * Guardar XML
     */
    protected function saveXml(string $xml, string $uuid): string
    {
        $path = "invoices/xml/{$uuid}.xml";
        Storage::disk('local')->put($path, $xml);
        return $path;
    }

    /**
     * Generar y guardar PDF
     */
    protected function generateAndSavePdfForInvoice(Invoice $invoice, string $xml): array
    {
        $payload = [
            'xmlContent' => $xml,
            'templateId' => 'cfdi40',
        ];

        // Agregar logo si existe
        $logoPath = 'company/logo.png';
        if (Storage::disk('local')->exists($logoPath)) {
            $payload['logo'] = base64_encode(Storage::disk('local')->get($logoPath));
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/pdf/v1/api/GeneratePdf', $payload);

        $data = $response->json();

        if ($response->successful() && isset($data['data']['contentB64'])) {
            $pdfPath = "invoices/pdf/{$invoice->cfdi_uuid}.pdf";
            Storage::disk('local')->put($pdfPath, base64_decode($data['data']['contentB64']));
            
            $invoice->update(['cfdi_pdf_path' => $pdfPath]);

            return [
                'success' => true,
                'path' => $pdfPath,
            ];
        }

        Log::warning('Error generando PDF', [
            'invoice_id' => $invoice->id,
            'response' => $data,
        ]);

        return [
            'success' => false,
            'message' => $data['message'] ?? 'Error al generar PDF',
        ];
    }

    /**
     * Generar PDF de una factura existente
     */
    public function regeneratePdf(Invoice $invoice): array
    {
        if (!$invoice->cfdi_xml_path || !Storage::disk('local')->exists($invoice->cfdi_xml_path)) {
            return [
                'success' => false,
                'message' => 'XML no encontrado',
            ];
        }

        $xml = Storage::disk('local')->get($invoice->cfdi_xml_path);
        return $this->generateAndSavePdfForInvoice($invoice, $xml);
    }

    // ==================== MÉTODOS DE CANCELACIÓN ====================

    /**
     * Cancelar factura
     */
    public function cancelInvoice(Invoice $invoice, string $motivo = '02', ?string $folioSustitucion = null): array
    {
        if (!$invoice->cfdi_uuid) {
            return [
                'success' => false,
                'message' => 'Factura no tiene UUID de timbrado',
            ];
        }

        $rfcEmisor = $this->emisor['Rfc'];
        $endpoint = "{$this->baseUrl}/cfdi33/cancel/{$rfcEmisor}/{$invoice->cfdi_uuid}/{$motivo}";

        if ($motivo === '01' && $folioSustitucion) {
            $endpoint .= "/{$folioSustitucion}";
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
        ])->post($endpoint);

        $data = $response->json();

        if ($response->successful() && isset($data['status']) && $data['status'] === 'success') {
            $invoice->update([
                'status' => 'canceled',
                'cfdi_canceled_at' => now(),
                'cfdi_cancellation_status' => 'cancelled',
                'cfdi_cancellation_reason' => $this->getCancellationReasonText($motivo),
            ]);

            return [
                'success' => true,
                'message' => 'Factura cancelada exitosamente',
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? 'Error al cancelar',
            'data' => $data,
        ];
    }

    /**
     * Obtener texto de razón de cancelación
     */
    protected function getCancellationReasonText(string $motivo): string
    {
        return match($motivo) {
            '01' => 'Comprobante emitido con errores con relación',
            '02' => 'Comprobante emitido con errores sin relación',
            '03' => 'No se llevó a cabo la operación',
            '04' => 'Operación nominativa relacionada en factura global',
            default => 'Motivo no especificado',
        };
    }

    // ==================== MÉTODOS DE DESCARGA ====================

    /**
     * Obtener contenido del XML
     */
    public function getXmlContent(Invoice $invoice): ?string
    {
        if (!$invoice->cfdi_xml_path || !Storage::disk('local')->exists($invoice->cfdi_xml_path)) {
            return null;
        }

        return Storage::disk('local')->get($invoice->cfdi_xml_path);
    }

    /**
     * Obtener contenido del PDF
     */
    public function getPdfContent(Invoice $invoice): ?string
    {
        if (!$invoice->cfdi_pdf_path || !Storage::disk('local')->exists($invoice->cfdi_pdf_path)) {
            return null;
        }

        return Storage::disk('local')->get($invoice->cfdi_pdf_path);
    }

    /**
     * Obtener ruta completa del XML
     */
    public function getXmlPath(Invoice $invoice): ?string
    {
        if (!$invoice->cfdi_xml_path) {
            return null;
        }

        return Storage::disk('local')->path($invoice->cfdi_xml_path);
    }

    /**
     * Obtener ruta completa del PDF
     */
    public function getPdfPath(Invoice $invoice): ?string
    {
        if (!$invoice->cfdi_pdf_path) {
            return null;
        }

        return Storage::disk('local')->path($invoice->cfdi_pdf_path);
    }

    // ==================== HELPERS ====================

    /**
     * Construir objeto BillingInfo desde array
     */
    protected function buildBillingInfoFromArray(array $data): CompanyBillingInfo
    {
        $billingInfo = new CompanyBillingInfo();
        $billingInfo->rfc = $data['rfc'];
        $billingInfo->legal_name = $data['razon_social'] ?? $data['legal_name'];
        $billingInfo->fiscal_regime = $data['regimen_fiscal'] ?? $data['fiscal_regime'];
        $billingInfo->postal_code = $data['codigo_postal'] ?? $data['postal_code'];
        $billingInfo->cfdi_use = $data['uso_cfdi'] ?? $data['cfdi_use'] ?? 'G03';
        $billingInfo->payment_form = $data['metodo_pago'] ?? $data['payment_form'] ?? 'PUE';
        $billingInfo->payment_method = $data['forma_pago'] ?? $data['payment_method'] ?? '03';
        $billingInfo->tax_regime = $data['tax_regime'] ?? $data['regimen_fiscal'] ?? $data['fiscal_regime'];

        return $billingInfo;
    }

    /**
     * Verificar si una compañía puede facturar automáticamente
     */
    public function canAutoInvoice(Company $company): bool
    {
        $billingInfo = $company->billingInfo;
        
        return $billingInfo 
            && $billingInfo->isComplete() 
            && $billingInfo->auto_request_invoice;
    }
}
