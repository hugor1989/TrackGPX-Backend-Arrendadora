<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenPayService
{
    protected string $merchantId;
    protected string $privateKey;
    protected string $publicKey;
    protected string $apiUrl;
    protected bool $sandbox;

    public function __construct()
    {
        $this->merchantId = config('openpay.merchant_id');
        $this->privateKey = config('openpay.private_key');
        $this->publicKey = config('openpay.public_key');
        $this->sandbox = config('openpay.environment') === 'sandbox';

        $environment = $this->sandbox ? 'sandbox' : 'production';
        $this->apiUrl = config("openpay.api_url.{$environment}") . "/{$this->merchantId}";
    }

    /**
     * Crear cliente en OpenPay
     */
    public function createCustomer(array $data): array
    {
        try {
            // Construir payload básico (campos obligatorios)
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            // Agregar campos opcionales solo si existen y no están vacíos
            if (!empty($data['phone'])) {
                $payload['phone_number'] = $data['phone'];
            }

            if (!empty($data['external_id'])) {
                $payload['external_id'] = $data['external_id'];
            }

            Log::info("Enviando a OpenPay", ['payload' => $payload]);

            // OpenPay requiere customer_address como objeto completo o no enviarlo
            /*  if (!empty($data['address'])) {
                $payload['address'] = [
                    'line1' => $data['address'],
                    'line2' => null,
                    'line3' => null,
                    'postal_code' => null,
                    'state' => null,
                    'city' => null,
                    'country_code' => 'MX',
                ];
            } */

            // NO enviar customer_address si no tienes dirección completa
            // OpenPay rechaza campos vacíos o mal formados

            $response = $this->makeRequest('POST', '/customers', $payload);

            Log::info("Respuesta de OpenPay", ['response' => $response]);


            $this->logRequest('createCustomer', $payload, $response);

            return [
                'success' => true,
                'customer_id' => $response['id'],
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('createCustomer', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener información del cliente
     */
    public function getCustomer(string $customerId): array
    {
        try {
            $response = $this->makeRequest('GET', "/customers/{$customerId}");

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('getCustomer', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Actualizar cliente
     */
    public function updateCustomer(string $customerId, array $data): array
    {
        try {
            $response = $this->makeRequest('PUT', "/customers/{$customerId}", $data);

            $this->logRequest('updateCustomer', $data, $response);

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('updateCustomer', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Eliminar cliente
     */
    public function deleteCustomer(string $customerId): array
    {
        try {
            $this->makeRequest('DELETE', "/customers/{$customerId}");

            return [
                'success' => true,
                'message' => 'Cliente eliminado correctamente',
            ];
        } catch (Exception $e) {
            $this->logError('deleteCustomer', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Agregar tarjeta a un cliente
     */
    public function addCard(string $customerId, string $tokenId, ?string $deviceSessionId = null): array
    {
        try {
            $payload = [
                'token_id' => $tokenId,
            ];

            if ($deviceSessionId) {
                $payload['device_session_id'] = $deviceSessionId;
            }

            $response = $this->makeRequest('POST', "/customers/{$customerId}/cards", $payload);

            $this->logRequest('addCard', ['customer_id' => $customerId], $response);

            return [
                'success' => true,
                'card_id' => $response['id'],
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('addCard', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Listar tarjetas de un cliente
     */
    public function getCards(string $customerId): array
    {
        try {
            $response = $this->makeRequest('GET', "/customers/{$customerId}/cards");

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('getCards', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener una tarjeta específica
     */
    public function getCard(string $customerId, string $cardId): array
    {
        try {
            $response = $this->makeRequest('GET', "/customers/{$customerId}/cards/{$cardId}");

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('getCard', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Eliminar tarjeta
     */
    public function deleteCard(string $customerId, string $cardId): array
    {
        try {
            $this->makeRequest('DELETE', "/customers/{$customerId}/cards/{$cardId}");

            return [
                'success' => true,
                'message' => 'Tarjeta eliminada correctamente',
            ];
        } catch (Exception $e) {
            $this->logError('deleteCard', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Crear cargo
     */
    public function createCharge(array $data): array
    {
        try {
            $customerId = $data['customer_id'] ?? null;

            $payload = [
                'source_id' => $data['card_id'],
                'method' => 'card',
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? config('openpay.currency'),
                'description' => $data['description'] ?? config('openpay.charge_description'),
            ];

            // Campos opcionales
            if (!empty($data['order_id'])) {
                $payload['order_id'] = $data['order_id'];
            }

            if (!empty($data['device_session_id'])) {
                $payload['device_session_id'] = $data['device_session_id'];
            }

            // ✅ SI HAY CUSTOMER_ID: usar endpoint del customer
            if ($customerId) {
                $endpoint = "/customers/{$customerId}/charges";
            }
            // ❌ SI NO HAY CUSTOMER_ID: usar endpoint general (requiere objeto customer)
            else {
                $endpoint = '/charges';

                // Para este caso necesitarías agregar el objeto customer
                if (!empty($data['customer'])) {
                    $payload['customer'] = $data['customer'];
                }
            }

            $response = $this->makeRequest('POST', $endpoint, $payload);

            $this->logRequest('createCharge', $payload, $response);

            return [
                'success' => true,
                'charge_id' => $response['id'],
                'transaction_id' => $response['authorization'] ?? null,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('createCharge', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener información de un cargo
     */
    public function getCharge(string $chargeId): array
    {
        try {
            $response = $this->makeRequest('GET', "/charges/{$chargeId}");

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('getCharge', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reembolsar un cargo
     */
    public function refundCharge(string $chargeId, ?float $amount = null, ?string $description = null): array
    {
        try {
            $payload = [];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            if ($description !== null) {
                $payload['description'] = $description;
            }

            $response = $this->makeRequest('POST', "/charges/{$chargeId}/refund", $payload);

            $this->logRequest('refundCharge', $payload, $response);

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('refundCharge', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Realizar petición HTTP a la API de OpenPay
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->apiUrl . $endpoint;

        $response = Http::withBasicAuth($this->privateKey, '')
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->$method($url, $data);

        if (!$response->successful()) {
            $error = $response->json();
            throw new Exception(
                $error['description'] ?? 'Error en la petición a OpenPay',
                $response->status()
            );
        }

        return $response->json();
    }

    /**
     * Log de peticiones
     */
    protected function logRequest(string $action, array $request, array $response): void
    {
        if (config('openpay.log_requests')) {
            Log::channel(config('openpay.log_channel'))->info("OpenPay {$action}", [
                'request' => $request,
                'response' => $response,
            ]);
        }
    }

    /**
     * Log de errores
     */
    protected function logError(string $action, Exception $e): void
    {
        Log::channel(config('openpay.log_channel'))->error("OpenPay {$action} Error", [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Verificar si está en modo sandbox
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Obtener la llave pública
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * ==================== PLANES/SUSCRIPCIONES ====================
     */

    /**
     * Crear plan en OpenPay
     */
    public function createPlan(array $data): array
    {
        try {
            $payload = [
                'name' => $data['name'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'MXN',
                'repeat_every' => $data['repeat_every'],
                'repeat_unit' => $data['repeat_unit'], // day, week, month, year
            ];

            // Campos opcionales
            if (!empty($data['status_after_retry'])) {
                $payload['status_after_retry'] = $data['status_after_retry'];
            }

            if (!empty($data['retry_times'])) {
                $payload['retry_times'] = $data['retry_times'];
            }

            if (!empty($data['trial_days'])) {
                $payload['trial_days'] = $data['trial_days'];
            }

            $response = $this->makeRequest('POST', '/plans', $payload);

            $this->logRequest('createPlan', $payload, $response);

            return [
                'success' => true,
                'plan_id' => $response['id'],
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('createPlan', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener información de un plan
     */
    public function getPlan(string $planId): array
    {
        try {
            $response = $this->makeRequest('GET', "/plans/{$planId}");

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('getPlan', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Actualizar un plan
     */
    public function updatePlan(string $planId, array $data): array
    {
        try {
            $payload = [];

            if (isset($data['name'])) {
                $payload['name'] = $data['name'];
            }

            if (isset($data['trial_days'])) {
                $payload['trial_days'] = $data['trial_days'];
            }

            $response = $this->makeRequest('PUT', "/plans/{$planId}", $payload);

            $this->logRequest('updatePlan', $payload, $response);

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('updatePlan', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Eliminar un plan
     */
    public function deletePlan(string $planId): array
    {
        try {
            $this->makeRequest('DELETE', "/plans/{$planId}");

            return [
                'success' => true,
                'message' => 'Plan eliminado correctamente',
            ];
        } catch (Exception $e) {
            $this->logError('deletePlan', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Listar todos los planes
     */
    public function listPlans(): array
    {
        try {
            $response = $this->makeRequest('GET', '/plans');

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('listPlans', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Suscribir un customer a un plan
     */
    public function subscribeCustomerToPlan(string $customerId, array $data): array
    {
        try {
            $payload = [
                'plan_id' => $data['plan_id'],
                'trial_end_date' => $data['trial_end_date'] ?? null,
                'card_id' => $data['card_id'] ?? null, // ID de la tarjeta
            ];

            $response = $this->makeRequest('POST', "/customers/{$customerId}/subscriptions", $payload);

            $this->logRequest('subscribeCustomerToPlan', $payload, $response);

            return [
                'success' => true,
                'subscription_id' => $response['id'],
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('subscribeCustomerToPlan', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener suscripciones de un customer
     */
    public function getCustomerSubscriptions(string $customerId): array
    {
        try {
            $response = $this->makeRequest('GET', "/customers/{$customerId}/subscriptions");

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            $this->logError('getCustomerSubscriptions', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancelar una suscripción
     */
    public function cancelSubscription(string $customerId, string $subscriptionId): array
    {
        try {
            $this->makeRequest('DELETE', "/customers/{$customerId}/subscriptions/{$subscriptionId}");

            return [
                'success' => true,
                'message' => 'Suscripción cancelada correctamente',
            ];
        } catch (Exception $e) {
            $this->logError('cancelSubscription', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
