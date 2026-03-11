<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\BillingCycle;
use App\Services\Billing\Gateways\OpenPayGateway;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio de Procesamiento de Pagos
 * 
 * Orquesta el procesamiento de pagos usando diferentes gateways.
 * Actualmente usa OpenPay, pero puede extenderse a otros gateways.
 */
class PaymentProcessingService
{
    protected OpenPayGateway $gateway;

    public function __construct(OpenPayGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Procesar pago de un ciclo de facturación
     */
    public function processBillingCyclePayment(BillingCycle $billingCycle): array
    {
        try {
            $company = $billingCycle->company;

            // Validar que la empresa tenga customer_id
            if (empty($company->openpay_customer_id)) {
                throw new Exception("La empresa no tiene un customer_id de OpenPay");
            }

            // Obtener la tarjeta por defecto de la empresa
            $defaultCard = $this->getDefaultPaymentMethod($company);

            if (!$defaultCard) {
                throw new Exception("La empresa no tiene un método de pago configurado");
            }

            // Preparar datos del cargo
            $chargeData = [
                'customer_id' => $company->openpay_customer_id,
                'payment_method_id' => $defaultCard['id'],
                'amount' => $billingCycle->total,
                'description' => "Pago de ciclo de facturación {$billingCycle->period_start->format('Y-m')}",
                'order_id' => "billing_cycle_{$billingCycle->id}",
            ];

            // Procesar el cargo
            $result = $this->gateway->charge($chargeData);

            if ($result['success']) {
                // Actualizar el billing cycle
                $billingCycle->markAsCharged([
                    'openpay_customer_id' => $company->openpay_customer_id,
                    'openpay_card_id' => $defaultCard['id'],
                    'openpay_charge_id' => $result['charge_id'],
                    'openpay_transaction_id' => $result['transaction_id'],
                ]);

                Log::info("Pago procesado exitosamente", [
                    'billing_cycle_id' => $billingCycle->id,
                    'company_id' => $company->id,
                    'amount' => $billingCycle->total,
                    'charge_id' => $result['charge_id'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Pago procesado exitosamente',
                    'charge_id' => $result['charge_id'],
                    'transaction_id' => $result['transaction_id'],
                ];
            } else {
                // Marcar como fallido
                $billingCycle->markAsFailed($result['error']);

                Log::error("Error al procesar pago", [
                    'billing_cycle_id' => $billingCycle->id,
                    'company_id' => $company->id,
                    'error' => $result['error'],
                ]);

                return [
                    'success' => false,
                    'error' => $result['error'],
                ];
            }
        } catch (Exception $e) {
            // Marcar como fallido
            $billingCycle->markAsFailed($e->getMessage());

            Log::error("Excepción al procesar pago", [
                'billing_cycle_id' => $billingCycle->id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Agregar método de pago a una empresa
     */
    public function addPaymentMethod(Company $company, string $tokenId, ?string $deviceSessionId = null): array
    {
        try {
            // Validar que tenga customer_id
            if (empty($company->openpay_customer_id)) {
                throw new Exception("La empresa no tiene un customer_id de OpenPay");
            }

            $result = $this->gateway->addPaymentMethod(
                $company->openpay_customer_id,
                [
                    'token_id' => $tokenId,
                    'device_session_id' => $deviceSessionId,
                ]
            );

            if ($result['success']) {
                Log::info("Método de pago agregado", [
                    'company_id' => $company->id,
                    'card_id' => $result['card_id'],
                ]);

                return [
                    'success' => true,
                    'card' => $this->gateway->formatPaymentMethod($result['data']),
                ];
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Error al agregar método de pago", [
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
     * Listar métodos de pago de una empresa
     */
    public function listPaymentMethods(Company $company): array
    {
        try {
            if (empty($company->openpay_customer_id)) {
                return [
                    'success' => true,
                    'cards' => [],
                ];
            }

            $result = $this->gateway->listPaymentMethods($company->openpay_customer_id);

            if ($result['success']) {
                $cards = array_map(
                    fn($card) => $this->gateway->formatPaymentMethod($card),
                    $result['data']
                );

                return [
                    'success' => true,
                    'cards' => $cards,
                ];
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Error al listar métodos de pago", [
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
     * Eliminar método de pago
     */
    public function deletePaymentMethod(Company $company, string $cardId): array
    {
        try {
            if (empty($company->openpay_customer_id)) {
                throw new Exception("La empresa no tiene un customer_id de OpenPay");
            }

            $result = $this->gateway->deletePaymentMethod(
                $company->openpay_customer_id,
                $cardId
            );

            if ($result['success']) {
                Log::info("Método de pago eliminado", [
                    'company_id' => $company->id,
                    'card_id' => $cardId,
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Error al eliminar método de pago", [
                'company_id' => $company->id,
                'card_id' => $cardId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener el método de pago por defecto
     * 
     * Por ahora retorna la primera tarjeta activa.
     * Puedes extenderlo para guardar una tarjeta por defecto en la BD.
     */
    protected function getDefaultPaymentMethod(Company $company): ?array
    {
        $result = $this->listPaymentMethods($company);

        if ($result['success'] && !empty($result['cards'])) {
            return $result['cards'][0]; // Primera tarjeta
        }

        return null;
    }

    /**
     * Reembolsar un pago
     */
    public function refundPayment(BillingCycle $billingCycle, ?float $amount = null, ?string $reason = null): array
    {
        try {
            if (empty($billingCycle->openpay_charge_id)) {
                throw new Exception("El ciclo de facturación no tiene un cargo asociado");
            }

            $result = $this->gateway->refund(
                $billingCycle->openpay_charge_id,
                $amount,
                $reason ?? 'Reembolso de pago'
            );

            if ($result['success']) {
                $billingCycle->update(['status' => 'refunded']);

                Log::info("Reembolso procesado", [
                    'billing_cycle_id' => $billingCycle->id,
                    'charge_id' => $billingCycle->openpay_charge_id,
                    'amount' => $amount ?? $billingCycle->total,
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Error al procesar reembolso", [
                'billing_cycle_id' => $billingCycle->id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reintentar pago fallido
     */
    public function retryFailedPayment(BillingCycle $billingCycle): array
    {
        // Verificar que sea reintentable
        if (!$billingCycle->can_retry) {
            return [
                'success' => false,
                'error' => 'El pago no puede ser reintentado (máximo de intentos alcanzado)',
            ];
        }

        return $this->processBillingCyclePayment($billingCycle);
    }

    /**
     * Obtener un método de pago específico
     */
    public function getPaymentMethod(Company $company, string $cardId): array
    {
        try {
            if (empty($company->openpay_customer_id)) {
                throw new Exception("La empresa no tiene un customer_id de OpenPay");
            }

            $result = $this->gateway->getPaymentMethod(
                $company->openpay_customer_id,
                $cardId
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'card' => $this->gateway->formatPaymentMethod($result['data']),
                ];
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Error al obtener método de pago", [
                'company_id' => $company->id,
                'card_id' => $cardId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener el gateway para operaciones directas
     */
    public function getGateway(): OpenPayGateway
    {
        return $this->gateway;
    }

    /**
     * Crear un cargo directo
     * 
     * Usado para cobros únicos, activaciones, etc.
     * 
     * @param array $data
     * @return array
     */
    public function createCharge(array $data): array
    {
        try {
            // Validar datos requeridos
            if (empty($data['card_id'])) {
                throw new Exception("Se requiere card_id (card_id) para crear el cargo");
            }

            if (empty($data['amount']) || $data['amount'] <= 0) {
                throw new Exception("El monto debe ser mayor a 0");
            }

            if (empty($data['description'])) {
                throw new Exception("Se requiere una descripción para el cargo");
            }

            // Crear el cargo usando el gateway
            $result = $this->gateway->createCharge($data);

            if ($result['success']) {
                Log::info("Cargo creado exitosamente", [
                    'charge_id' => $result['charge_id'],
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                ]);
            } else {
                Log::error("Error al crear cargo", [
                    'error' => $result['error'],
                    'data' => $data,
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Excepción al crear cargo", [
                'exception' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener tarjetas de un customer de OpenPay
     * 
     * @param string $customerId
     * @return array
     */
    public function getCustomerCards(string $customerId): array
    {
        try {
            $result = $this->gateway->listPaymentMethods($customerId);

            if ($result['success']) {
                return array_map(
                    fn($card) => $this->gateway->formatPaymentMethod($card),
                    $result['data']
                );
            }

            return [];
        } catch (Exception $e) {
            Log::error("Error al obtener tarjetas del customer", [
                'customer_id' => $customerId,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
    /**
     * Obtener la llave pública del gateway (para el frontend)
     */
    public function getPublicKey(): string
    {
        return $this->gateway->getPublicKey();
    }

    /**
     * Verificar si está en modo sandbox
     */
    public function isSandbox(): bool
    {
        return $this->gateway->isSandbox();
    }
}
