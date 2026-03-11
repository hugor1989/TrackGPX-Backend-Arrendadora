<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\DeviceSubscription;
use App\Models\Device;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Listar todas las suscripciones de la empresa
     * 
     * GET /api/billing/subscriptions
     */
    public function index(Request $request)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return $this->errorResponse('No se encontró la empresa del usuario', 404);
            }

            $subscriptions = DeviceSubscription::with(['device', 'plan'])
                ->forCompany($company->id)
                ->when($request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener suscripciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear una nueva suscripción
     * 
     * POST /api/billing/subscriptions
     * 
     * Body:
     * {
     *   "device_id": 1,
     *   "plan_id": 2,
     *   "billing_cycle": "monthly",
     *   "start_date": "2024-01-01"
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'start_date' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            $company = $this->getAuthenticatedCompany($request);

            if (!$company) {
                return $this->errorResponse('No se encontró la empresa del usuario', 404);
            }

            // Verificar que el dispositivo pertenezca a la empresa
            $device = Device::where('id', $request->device_id)
                ->first();

            // Verificar que no tenga una suscripción activa
            $activeSubscription = DeviceSubscription::where('device_id', $device->id)
                ->where('status', 'active')
                ->first();

            if ($activeSubscription) {
                return $this->errorResponse('El dispositivo ya tiene una suscripción activa', 400);
            }

            $plan = Plan::findOrFail($request->plan_id);
            $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now();
            $months = $request->billing_cycle === 'annual' ? 12 : 1;
            $endDate = $startDate->copy()->addMonths($months);

            // Crear la suscripción
            $subscription = DeviceSubscription::create([
                'device_id' => $device->id,
                'plan_id' => $plan->id,
                'company_id' => $company->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_billing_date' => $endDate,
                'status' => 'pending', // Se activará después del primer pago
                'amount' => $plan->price,
                'billing_cycle' => $request->billing_cycle,
                'auto_renew' => $request->auto_renew ?? true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Suscripción creada exitosamente',
                'data' => $subscription->load(['device', 'plan']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear suscripción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener detalles de una suscripción
     * 
     * GET /api/billing/subscriptions/{id}
     */
    public function show(Request $request, int $id)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            $subscription = DeviceSubscription::with(['device', 'plan'])
                ->forCompany($company->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $subscription,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Suscripción no encontrada', 404);
        }
    }

    /**
     * Pausar una suscripción
     * 
     * POST /api/billing/subscriptions/{id}/pause
     * 
     * Body:
     * {
     *   "reason": "Motivo de la pausa"
     * }
     */
    public function pause(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            $subscription = DeviceSubscription::forCompany($company->id)->findOrFail($id);

            if ($subscription->status !== 'active') {
                return $this->errorResponse('Solo se pueden pausar suscripciones activas', 400);
            }

            $subscription->pause($request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Suscripción pausada exitosamente',
                'data' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al pausar suscripción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reanudar una suscripción pausada
     * 
     * POST /api/billing/subscriptions/{id}/resume
     */
    public function resume(Request $request, int $id)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            $subscription = DeviceSubscription::forCompany($company->id)->findOrFail($id);

            if ($subscription->status !== 'paused') {
                return $this->errorResponse('Solo se pueden reanudar suscripciones pausadas', 400);
            }

            $subscription->resume();

            return response()->json([
                'success' => true,
                'message' => 'Suscripción reanudada exitosamente',
                'data' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al reanudar suscripción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancelar una suscripción
     * 
     * POST /api/billing/subscriptions/{id}/cancel
     * 
     * Body:
     * {
     *   "reason": "Motivo de la cancelación"
     * }
     */
    public function cancel(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $company = $this->getAuthenticatedCompany($request);

            $subscription = DeviceSubscription::forCompany($company->id)->findOrFail($id);

            if (in_array($subscription->status, ['canceled', 'expired'])) {
                return $this->errorResponse('La suscripción ya está cancelada o expirada', 400);
            }

            $subscription->cancel($request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Suscripción cancelada exitosamente',
                'data' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al cancelar suscripción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Renovar una suscripción manualmente
     * 
     * POST /api/billing/subscriptions/{id}/renew
     */
    public function renew(Request $request, int $id)
    {
        try {
            $company = $this->getAuthenticatedCompany($request);

            $subscription = DeviceSubscription::forCompany($company->id)->findOrFail($id);

            if ($subscription->status === 'canceled') {
                return $this->errorResponse('No se puede renovar una suscripción cancelada', 400);
            }

            $subscription->renew();

            return response()->json([
                'success' => true,
                'message' => 'Suscripción renovada exitosamente',
                'data' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al renovar suscripción: ' . $e->getMessage(), 500);
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