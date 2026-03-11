<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\OpenPayService;
use Illuminate\Support\Facades\Log;

class PlanObserver
{
    protected OpenPayService $openPayService;

    public function __construct(OpenPayService $openPayService)
    {
        $this->openPayService = $openPayService;
    }

    /**
     * esto es una prueba
     * Handle the Plan "created" event.
     * 
     * Se ejecuta automáticamente después de crear un plan
     */
    public function created(Plan $plan): void
    {
        // Solo crear plan si no existe ya en OpenPay
        if (!empty($plan->openpay_plan_id)) {
            return;
        }

        try {
            // Mapear interval de Laravel a formato OpenPay
            $intervalMap = [
                'day' => 'day',
                'week' => 'week',
                'month' => 'month',
                'year' => 'year',
            ];

            $repeatUnit = $intervalMap[$plan->interval] ?? 'month';

            // Crear plan en OpenPay
            $result = $this->openPayService->createPlan([
                'name' => $plan->name,
                'amount' => (float) $plan->price,
                'currency' => $plan->currency,
                'repeat_every' => $plan->interval_count,
                'repeat_unit' => $repeatUnit,
                'retry_times' => 3, // Reintentar 3 veces si falla el cobro
                'status_after_retry' => 'cancelled', // Cancelar si fallan todos los reintentos
            ]);

            if ($result['success']) {
                // Guardar el plan_id en el plan
                $plan->update([
                    'openpay_plan_id' => $result['plan_id'],
                ]);

                Log::info("OpenPay plan creado", [
                    'plan_id' => $plan->id,
                    'openpay_plan_id' => $result['plan_id'],
                    'name' => $plan->name,
                    'price' => $plan->price,
                ]);
            } else {
                Log::error("Error al crear OpenPay plan", [
                    'plan_id' => $plan->id,
                    'error' => $result['error'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Excepción al crear OpenPay plan", [
                'plan_id' => $plan->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Plan "updated" event.
     * 
     * Sincronizar cambios con OpenPay cuando se actualiza el plan
     */
    public function updated(Plan $plan): void
    {
        // Solo sincronizar si ya tiene openpay_plan_id
        if (empty($plan->openpay_plan_id)) {
            return;
        }

        // OpenPay solo permite actualizar nombre y trial_days
        // El precio y frecuencia NO se pueden cambiar
        $relevantFields = ['name'];
        $hasChanges = false;

        foreach ($relevantFields as $field) {
            if ($plan->isDirty($field)) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            return;
        }

        try {
            // Actualizar en OpenPay
            $updateData = [
                'name' => $plan->name,
            ];

            $result = $this->openPayService->updatePlan(
                $plan->openpay_plan_id,
                $updateData
            );

            if ($result['success']) {
                Log::info("OpenPay plan actualizado", [
                    'plan_id' => $plan->id,
                    'openpay_plan_id' => $plan->openpay_plan_id,
                ]);
            } else {
                Log::error("Error al actualizar OpenPay plan", [
                    'plan_id' => $plan->id,
                    'error' => $result['error'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Excepción al actualizar OpenPay plan", [
                'plan_id' => $plan->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Plan "deleted" event.
     * 
     * Eliminar plan de OpenPay cuando se elimina localmente
     */
    public function deleted(Plan $plan): void
    {
        if (!empty($plan->openpay_plan_id)) {
            try {
                $result = $this->openPayService->deletePlan($plan->openpay_plan_id);
                
                if ($result['success']) {
                    Log::info("OpenPay plan eliminado", [
                        'plan_id' => $plan->id,
                        'openpay_plan_id' => $plan->openpay_plan_id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Error al eliminar OpenPay plan", [
                    'plan_id' => $plan->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the Plan "deleting" event.
     * 
     * Validar antes de eliminar
     */
    public function deleting(Plan $plan): bool
    {
        // Verificar si hay suscripciones activas
        $activeSubscriptions = $plan->subscriptions()
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($activeSubscriptions > 0) {
            Log::warning("Intento de eliminar plan con suscripciones activas", [
                'plan_id' => $plan->id,
                'active_subscriptions' => $activeSubscriptions,
            ]);

            // Cambiar a inactivo en lugar de eliminar
            $plan->status = 'inactive';
            $plan->saveQuietly(); // Save sin disparar eventos

            return false; // Cancelar eliminación
        }

        return true; // Permitir eliminación
    }
}