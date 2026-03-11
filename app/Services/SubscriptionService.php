<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DeviceSubscription;
use App\Models\Plan;
use App\Services\OpenPayService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio de Suscripciones con OpenPay
 * 
 * Maneja la creación de suscripciones recurrentes en OpenPay
 */
class SubscriptionService
{
    protected OpenPayService $openPayService;

    public function __construct(OpenPayService $openPayService)
    {
        $this->openPayService = $openPayService;
    }

    /**
     * Suscribir una empresa a un plan en OpenPay
     */
    public function subscribeCompanyToPlan(
        Company $company,
        Plan $plan,
        ?string $cardId = null,
        ?string $trialEndDate = null
    ): array {
        try {
            // Validaciones
            if (empty($company->openpay_customer_id)) {
                throw new Exception("La empresa no tiene un customer_id de OpenPay");
            }

            if (empty($plan->openpay_plan_id)) {
                throw new Exception("El plan no tiene un plan_id de OpenPay");
            }

            // Preparar datos de suscripción
            $subscriptionData = [
                'plan_id' => $plan->openpay_plan_id,
            ];

            if ($cardId) {
                $subscriptionData['card_id'] = $cardId;
            }

            if ($trialEndDate) {
                $subscriptionData['trial_end_date'] = $trialEndDate;
            }

            // Crear suscripción en OpenPay
            $result = $this->openPayService->subscribeCustomerToPlan(
                $company->openpay_customer_id,
                $subscriptionData
            );

            if ($result['success']) {
                Log::info("Suscripción creada en OpenPay", [
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'openpay_subscription_id' => $result['subscription_id'],
                ]);

                return [
                    'success' => true,
                    'subscription_id' => $result['subscription_id'],
                    'data' => $result['data'],
                ];
            }

            return $result;

        } catch (Exception $e) {
            Log::error("Error al crear suscripción en OpenPay", [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Listar suscripciones de una empresa en OpenPay
     */
    public function listCompanySubscriptions(Company $company): array
    {
        try {
            if (empty($company->openpay_customer_id)) {
                return [
                    'success' => true,
                    'subscriptions' => [],
                ];
            }

            $result = $this->openPayService->getCustomerSubscriptions(
                $company->openpay_customer_id
            );

            return $result;

        } catch (Exception $e) {
            Log::error("Error al listar suscripciones", [
                'company_id' => $company->id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancelar suscripción en OpenPay
     */
    public function cancelSubscription(
        Company $company,
        string $openPaySubscriptionId
    ): array {
        try {
            if (empty($company->openpay_customer_id)) {
                throw new Exception("La empresa no tiene un customer_id de OpenPay");
            }

            $result = $this->openPayService->cancelSubscription(
                $company->openpay_customer_id,
                $openPaySubscriptionId
            );

            if ($result['success']) {
                Log::info("Suscripción cancelada en OpenPay", [
                    'company_id' => $company->id,
                    'subscription_id' => $openPaySubscriptionId,
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("Error al cancelar suscripción", [
                'company_id' => $company->id,
                'subscription_id' => $openPaySubscriptionId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sincronizar estado de suscripciones locales con OpenPay
     */
    public function syncSubscriptionStatus(DeviceSubscription $subscription): array
    {
        try {
            // TODO: Implementar lógica de sincronización
            // Obtener estado desde OpenPay y actualizar local
            
            return [
                'success' => true,
                'message' => 'Sincronización pendiente de implementar',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}