<?php

namespace App\Services\Billing\Gateways;

use Illuminate\Support\Facades\Log;

use App\Services\OpenPayService;
use Exception;

/**
 * Gateway de OpenPay
 * 
 * Implementa la interfaz común para procesadores de pago.
 * Esto permite cambiar fácilmente entre diferentes gateways (Stripe, Conekta, etc.)
 */
class OpenpayGateway
{
    protected OpenPayService $openPayService;

    public function __construct(OpenPayService $openPayService)
    {
        $this->openPayService = $openPayService;
    }

    /**
     * Crear cliente/customer en el gateway
     */
    public function createCustomer(array $data): array
    {
        return $this->openPayService->createCustomer([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'rfc' => $data['rfc'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }

    /**
     * Obtener información del customer
     */
    public function getCustomer(string $customerId): array
    {
        return $this->openPayService->getCustomer($customerId);
    }

    /**
     * Actualizar información del customer
     */
    public function updateCustomer(string $customerId, array $data): array
    {
        return $this->openPayService->updateCustomer($customerId, $data);
    }

    /**
     * Agregar método de pago (tarjeta)
     */
    public function addPaymentMethod(string $customerId, array $data): array
    {
        return $this->openPayService->addCard(
            $customerId,
            $data['token_id'],
            $data['device_session_id'] ?? null
        );
    }

    /**
     * Listar métodos de pago
     */
    public function listPaymentMethods(string $customerId): array
    {
        return $this->openPayService->getCards($customerId);
    }

    /**
     * Obtener un método de pago específico
     */
    public function getPaymentMethod(string $customerId, string $paymentMethodId): array
    {
        return $this->openPayService->getCard($customerId, $paymentMethodId);
    }

    /**
     * Eliminar método de pago
     */
    public function deletePaymentMethod(string $customerId, string $paymentMethodId): array
    {
        return $this->openPayService->deleteCard($customerId, $paymentMethodId);
    }

    /**
     * Procesar un cargo/pago
     */
    public function charge(array $data): array
    {
        // Validar datos requeridos
        $this->validateChargeData($data);

        return $this->openPayService->createCharge([
            'customer_id' => $data['customer_id'],
            'card_id' => $data['payment_method_id'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? 'Cargo de suscripción GPS',
            'order_id' => $data['order_id'] ?? null,
            'device_session_id' => $data['device_session_id'] ?? null,
        ]);
    }

    /**
     * Obtener información de un cargo
     */
    public function getCharge(string $chargeId): array
    {
        return $this->openPayService->getCharge($chargeId);
    }

    /**
     * Reembolsar un cargo
     */
    public function refund(string $chargeId, ?float $amount = null, ?string $reason = null): array
    {
        return $this->openPayService->refundCharge($chargeId, $amount, $reason);
    }

    /**
     * Validar datos requeridos para un cargo
     */
    protected function validateChargeData(array $data): void
    {
        $required = ['customer_id', 'payment_method_id', 'amount'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("El campo '{$field}' es requerido para procesar el cargo");
            }
        }

        if ($data['amount'] <= 0) {
            throw new Exception("El monto debe ser mayor a 0");
        }
    }

    /**
     * Obtener el nombre del gateway
     */
    public function getName(): string
    {
        return 'openpay';
    }

    /**
     * Verificar si está en modo sandbox
     */
    public function isSandbox(): bool
    {
        return $this->openPayService->isSandbox();
    }

    /**
     * Obtener la llave pública (para el frontend)
     */
    public function getPublicKey(): string
    {
        return $this->openPayService->getPublicKey();
    }

    /**
     * Formatear respuesta de método de pago para uso interno
     */
    public function formatPaymentMethod(array $cardData): array
    {
        return [
            'id' => $cardData['id'],
            'brand' => $cardData['brand'] ?? $cardData['card_type'] ?? 'unknown',
            'last_four' => $cardData['card_number'] ?? '****',
            'expiration_month' => $cardData['expiration_month'] ?? null,
            'expiration_year' => $cardData['expiration_year'] ?? null,
            'holder_name' => $cardData['holder_name'] ?? null,
            'bank_name' => $cardData['bank_name'] ?? null,
            'bank_code' => $cardData['bank_code'] ?? null,
            'type' => $cardData['type'] ?? 'credit',
        ];
    }

    /**
     * Formatear respuesta de cargo para uso interno
     */
    public function formatCharge(array $chargeData): array
    {
        return [
            'id' => $chargeData['id'],
            'amount' => $chargeData['amount'],
            'currency' => $chargeData['currency'] ?? 'MXN',
            'status' => $chargeData['status'],
            'description' => $chargeData['description'] ?? null,
            'order_id' => $chargeData['order_id'] ?? null,
            'authorization' => $chargeData['authorization'] ?? null,
            'transaction_type' => $chargeData['transaction_type'] ?? null,
            'operation_type' => $chargeData['operation_type'] ?? null,
            'error_message' => $chargeData['error_message'] ?? null,
            'created_at' => $chargeData['creation_date'] ?? null,
        ];
    }

    /**
     * Crear un cargo directo (flexible para diferentes casos de uso)
     * 
     * @param array $data [
     *   'card_id' => string (card_id),
     *   'method' => string (card),
     *   'amount' => float,
     *   'currency' => string (MXN),
     *   'description' => string,
     *   'order_id' => string (opcional),
     *   'device_session_id' => string (opcional),
     *   'customer' => array (opcional) [
     *     'name' => string,
     *     'email' => string,
     *   ]
     * ]
     */
    public function createCharge(array $data): array
    {
        try {
            $chargeData = [
                'method' => $data['method'] ?? 'card',
                'card_id' => $data['card_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'MXN',
                'description' => $data['description'],
            ];

            // ✅ Customer ID (requerido para cargos con tarjeta guardada)
            if (!empty($data['customer_id'])) {
                $chargeData['customer_id'] = $data['customer_id'];
            }

            // Campos opcionales
            if (!empty($data['order_id'])) {
                $chargeData['order_id'] = $data['order_id'];
            }

            if (!empty($data['device_session_id'])) {
                $chargeData['device_session_id'] = $data['device_session_id'];
            }

           

            $response = $this->openPayService->createCharge($chargeData);

            if ($response['success']) {
                return [
                    'success' => true,
                    'charge_id' => $response['data']['id'] ?? null,
                    'transaction_id' => $response['data']['authorization'] ?? null,
                    'data' => $response['data'],
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Error desconocido al procesar el cargo',
                'error_code' => $response['error_code'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error("Error en OpenPayGateway::createCharge", [
                'exception' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
