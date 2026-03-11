<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\DeviceSubscription;
use App\Services\Billing\PaymentProcessingService;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceActivationController extends Controller
{
    protected PaymentProcessingService $paymentService;
    protected InvoiceService $invoiceService;

    public function __construct(
        PaymentProcessingService $paymentService,
        InvoiceService $invoiceService
    ) {
        $this->paymentService = $paymentService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Activar dispositivo con plan y pago
     * 
     * POST /api/devices/activate
     * 
     * Flujo completo:
     * 1. Valida IMEI + activation_code
     * 2. Vincula device a la empresa
     * 3. Procesa pago del plan
     * 4. Registra el pago en DB
     * 5. Crea suscripción activa
     * 6. Genera factura (si auto_invoice está habilitado)
     */
    public function activate(Request $request)
    {
        $request->validate([
            'imei' => 'required|string|size:15',
            'activation_code' => 'required|string|size:9',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'card_id' => 'required|string',
            'device_session_id' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            if (!$company->openpay_customer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa no tiene configurado OpenPay',
                ], 400);
            }

            // Validar código de activación
            $device = Device::validateActivationCode(
                $request->imei,
                $request->activation_code
            );

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código de activación inválido o dispositivo ya activado',
                ], 400);
            }

            Log::info("Iniciando activación de dispositivo", [
                'device_id' => $device->id,
                'imei' => $device->imei,
                'company_id' => $company->id,
            ]);

            // Obtener plan
            $plan = Plan::findOrFail($request->plan_id);

            if ($plan->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'El plan seleccionado no está disponible',
                ], 400);
            }

            $startDate = now();

            if ($request->billing_cycle === 'annual') {
                $endDate = $startDate->copy()->addYear();
            } else {
                $endDate = $startDate->copy()->addMonth();
            }

            // ✅ NUEVO: Crear registro de pago pendiente
            $payment = Payment::createForActivation(
                companyId: $company->id,
                deviceId: $device->id,
                subscriptionId: null, // Se actualizará después
                amount: (float) $plan->price,
                description: "Activación GPS - {$plan->name} ({$request->billing_cycle})"
            );

            Log::info("Procesando pago de activación", [
                'payment_id' => $payment->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'billing_cycle' => $request->billing_cycle,
            ]);

            // Procesar pago con OpenPay
            $paymentResult = $this->paymentService->createCharge([
                'customer_id' => $company->openpay_customer_id,
                'card_id' => $request->card_id,
                'method' => 'card',
                'amount' => (float) $plan->price,
                'currency' => $plan->currency ?? 'MXN',
                'description' => "Activación GPS - {$plan->name} ({$request->billing_cycle})",
                'order_id' => "ACTIVATION-{$device->id}-{$payment->id}",
                'device_session_id' => $request->device_session_id,
            ]);

            if (!$paymentResult['success']) {
                // ✅ Marcar pago como fallido
                $payment->markAsFailed(
                    $paymentResult['error_code'] ?? 'unknown',
                    $paymentResult['error'] ?? 'Error desconocido'
                );

                DB::rollBack();

                Log::error("Error en pago de activación", [
                    'device_id' => $device->id,
                    'payment_id' => $payment->id,
                    'error' => $paymentResult['error'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el pago: ' . $paymentResult['error'],
                    'error_code' => $paymentResult['error_code'] ?? null,
                ], 400);
            }

            // ✅ Marcar pago como completado
            $payment->markAsCompleted(
                chargeId: $paymentResult['charge_id'],
                authorizationCode: $paymentResult['transaction_id'] ?? null,
                cardInfo: [
                    'type' => $paymentResult['card']['type'] ?? null,
                    'last_four' => $paymentResult['card']['card_number'] ?? null,
                    'holder_name' => $paymentResult['card']['holder_name'] ?? null,
                    'bank_name' => $paymentResult['card']['bank_name'] ?? null,
                ]
            );

            Log::info("Pago procesado exitosamente", [
                'payment_id' => $payment->id,
                'charge_id' => $paymentResult['charge_id'],
                'amount' => $plan->price,
            ]);

            // Activar dispositivo
            $device->activate($company->id);

            Log::info("Dispositivo activado", [
                'device_id' => $device->id,
                'company_id' => $company->id,
            ]);

            // Crear suscripción activa
            $subscription = DeviceSubscription::create([
                'device_id' => $device->id,
                'plan_id' => $plan->id,
                'company_id' => $company->id,
                'amount' => $plan->price,
                'billing_cycle' => $request->billing_cycle,
                'currency' => $plan->currency ?? 'MXN',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_billing_date' => $endDate,
                'status' => 'active',
                'auto_renew' => true,
                'activated_at' => now(),
            ]);

            // ✅ Actualizar pago con subscription_id
            $payment->update(['device_subscription_id' => $subscription->id]);

            Log::info("Suscripción creada", [
                'subscription_id' => $subscription->id,
                'device_id' => $device->id,
                'plan_id' => $plan->id,
            ]);

            // ✅ NUEVO: Generar factura automáticamente si está habilitado
            $invoiceData = null;
            if ($this->invoiceService->canAutoInvoice($company)) {
                Log::info("Generando factura automática", [
                    'payment_id' => $payment->id,
                    'company_id' => $company->id,
                ]);

                $invoiceResult = $this->invoiceService->generateActivationInvoice(
                    $payment,
                    $subscription
                );

                if ($invoiceResult['success']) {
                    $invoiceData = [
                        'invoice_id' => $invoiceResult['invoice']->id,
                        'invoice_number' => $invoiceResult['invoice']->invoice_number,
                        'uuid' => $invoiceResult['uuid'],
                        'status' => 'issued',
                    ];

                    Log::info("Factura generada exitosamente", [
                        'invoice_id' => $invoiceResult['invoice']->id,
                        'uuid' => $invoiceResult['uuid'],
                    ]);
                } else {
                    Log::warning("No se pudo generar factura automática", [
                        'payment_id' => $payment->id,
                        'error' => $invoiceResult['message'] ?? 'Error desconocido',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo activado exitosamente',
                'data' => [
                    'device' => [
                        'id' => $device->id,
                        'imei' => $device->imei,
                        'model' => $device->model,
                        'manufacturer' => $device->manufacturer,
                        'status' => $device->status,
                        'activated_at' => $device->activated_at,
                    ],
                    'subscription' => [
                        'id' => $subscription->id,
                        'plan' => [
                            'id' => $plan->id,
                            'name' => $plan->name,
                            'price' => $plan->price,
                        ],
                        'amount' => $subscription->amount,
                        'billing_cycle' => $subscription->billing_cycle,
                        'status' => $subscription->status,
                        'start_date' => $subscription->start_date->format('Y-m-d'),
                        'end_date' => $subscription->end_date->format('Y-m-d'),
                        'next_billing_date' => $subscription->next_billing_date->format('Y-m-d'),
                        'auto_renew' => $subscription->auto_renew,
                    ],
                    'payment' => [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'tax' => $payment->tax,
                        'total' => $payment->total,
                        'currency' => $payment->currency,
                        'charge_id' => $payment->openpay_charge_id,
                        'authorization' => $payment->authorization_code,
                        'status' => $payment->status,
                        'description' => $payment->description,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                    ],
                    'invoice' => $invoiceData,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Error en activación de dispositivo", [
                'imei' => $request->imei,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al activar dispositivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar dispositivos de la empresa del usuario
     * 
     * GET /api/devices
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

            $devices = Device::where('company_id', $company->id)
                ->with(['vehicle', 'subscription.plan'])
                ->get()
                ->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'imei' => $device->imei,
                        'serial_number' => $device->serial_number,
                        'status' => $device->status,
                        'model' => $device->model,
                        'manufacturer' => $device->manufacturer,
                        'protocol' => $device->protocol,
                        // ✅ AGREGAR ESTO:
                        'vehicle' => $device->vehicle ? [
                            'id' => $device->vehicle->id,
                            'name' => $device->vehicle->name,
                            'plate' => $device->vehicle->plate
                        ] : null,
                        'subscription_status' => $device->subscription ? $device->subscription->status : 'sin_plan'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $devices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dispositivos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar dispositivo desde el servidor (auto-registro)
     * 
     * POST /api/devices/register
     */
    public function register(Request $request)
    {
        $request->validate([
            'imei' => 'required|string|size:15|unique:devices,imei',
            'serial_number' => 'nullable|string',
            'model' => 'nullable|string',
            'manufacturer' => 'nullable|string',
            'protocol' => 'nullable|string',
        ]);

        try {
            $device = Device::registerFromServer($request->all());

            Log::info("Dispositivo registrado desde servidor", [
                'device_id' => $device->id,
                'imei' => $device->imei,
                'activation_code' => $device->activation_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo registrado exitosamente',
                'data' => [
                    'id' => $device->id,
                    'imei' => $device->imei,
                    'activation_code' => $device->activation_code,
                    'status' => $device->status,
                    'model' => $device->model,
                    'manufacturer' => $device->manufacturer,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error al registrar dispositivo", [
                'imei' => $request->imei,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar dispositivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar última conexión del dispositivo
     * 
     * POST /api/devices/{imei}/heartbeat
     */
    public function heartbeat(string $imei)
    {
        try {
            $device = Device::where('imei', $imei)->firstOrFail();
            $device->updateLastConnection();

            return response()->json([
                'success' => true,
                'message' => 'Última conexión actualizada',
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dispositivo no encontrado',
            ], 404);
        }
    }

    /**
     * Obtener información de un dispositivo por IMEI (preview)
     * 
     * GET /api/devices/preview/{imei}
     */
    public function preview(string $imei)
    {
        try {
            $device = Device::where('imei', $imei)
                ->whereNull('company_id')
                ->where('status', 'available')
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'imei_preview' => substr($device->imei, 0, 6) . '******' . substr($device->imei, -3),
                    'model' => $device->model,
                    'manufacturer' => $device->manufacturer,
                    'protocol' => $device->protocol,
                    'status' => $device->status,
                    'requires_activation_code' => true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dispositivo no encontrado o no disponible',
            ], 404);
        }
    }

    /**
     * Listar dispositivos disponibles para asignar a vehículos
     * 
     * GET /api/devices/available
     */
    public function available(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa del usuario',
                ], 404);
            }

            $devices = Device::where('company_id', $company->id)
                ->where('status', 'active')
                ->whereNull('vehicle_id')
                ->with(['subscription.plan'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'imei' => $device->imei,
                        'serial_number' => $device->serial_number,
                        'model' => $device->model,
                        'manufacturer' => $device->manufacturer,
                        'protocol' => $device->protocol,
                        'status' => $device->status,
                        'vehicle_id' => $device->vehicle_id,
                        'is_activated' => $device->is_activated,
                        'is_online' => $device->is_online,
                        'activated_at' => $device->activated_at?->format('Y-m-d H:i:s'),
                        'last_connection_at' => $device->last_connection_at?->format('Y-m-d H:i:s'),
                        'created_at' => $device->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $device->updated_at->format('Y-m-d H:i:s'),
                        'subscription' => $device->subscription ? [
                            'id' => $device->subscription->id,
                            'status' => $device->subscription->status,
                            'plan_name' => $device->subscription->plan?->name,
                            'end_date' => $device->subscription->end_date?->format('Y-m-d'),
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $devices,
                'count' => $devices->count(),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error al listar dispositivos disponibles", [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dispositivos disponibles: ' . $e->getMessage(),
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
            return \App\Models\Company::find($user->company_id);
        }

        return null;
    }
}
