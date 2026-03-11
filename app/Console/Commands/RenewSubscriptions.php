<?php

namespace App\Console\Commands;

use App\Models\DeviceSubscription;
use App\Models\Payment;
use App\Services\Billing\PaymentProcessingService;
use App\Services\Billing\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RenewSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:renew 
                            {--dry-run : Simular sin procesar pagos reales}
                            {--limit=100 : Límite de suscripciones a procesar}
                            {--subscription= : Procesar una suscripción específica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa renovaciones automáticas de suscripciones vencidas';

    protected PaymentProcessingService $paymentService;
    protected InvoiceService $invoiceService;

    protected int $processed = 0;
    protected int $successful = 0;
    protected int $failed = 0;
    protected int $skipped = 0;

    public function __construct(
        PaymentProcessingService $paymentService,
        InvoiceService $invoiceService
    ) {
        parent::__construct();
        $this->paymentService = $paymentService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Iniciando proceso de renovación de suscripciones...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $specificId = $this->option('subscription');

        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se procesarán pagos reales');
            $this->newLine();
        }

        // Obtener suscripciones a renovar
        $subscriptions = $this->getSubscriptionsToRenew($limit, $specificId);

        if ($subscriptions->isEmpty()) {
            $this->info('✅ No hay suscripciones pendientes de renovación.');
            return Command::SUCCESS;
        }

        $this->info("📋 Suscripciones encontradas: {$subscriptions->count()}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($subscriptions->count());
        $progressBar->start();

        foreach ($subscriptions as $subscription) {
            $this->processRenewal($subscription, $isDryRun);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Resumen
        $this->printSummary();

        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Obtener suscripciones que necesitan renovación
     */
    protected function getSubscriptionsToRenew(int $limit, ?string $specificId)
    {
        $query = DeviceSubscription::query()
            ->with(['company.billingInfo', 'device', 'plan'])
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->where('next_billing_date', '<=', Carbon::today());

        if ($specificId) {
            $query->where('id', $specificId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Procesar renovación de una suscripción
     */
    protected function processRenewal(DeviceSubscription $subscription, bool $isDryRun): void
    {
        $this->processed++;

        $company = $subscription->company;
        $device = $subscription->device;

        Log::info("Procesando renovación", [
            'subscription_id' => $subscription->id,
            'company_id' => $company->id,
            'device_imei' => $device->imei ?? 'N/A',
            'amount' => $subscription->amount,
        ]);

        // Validaciones
        if (!$company->openpay_customer_id) {
            $this->logSkipped($subscription, 'Empresa sin OpenPay configurado');
            return;
        }

        // Buscar método de pago predeterminado
        $defaultCard = $this->getDefaultPaymentMethod($company);
        if (!$defaultCard) {
            $this->logSkipped($subscription, 'Sin método de pago configurado');
            $this->markAsPaymentFailed($subscription, 'No hay método de pago configurado');
            return;
        }

        if ($isDryRun) {
            $this->logDryRun($subscription);
            $this->successful++;
            return;
        }

        // Procesar renovación real
        $this->executeRenewal($subscription, $company, $defaultCard);
    }

    /**
     * Ejecutar renovación real
     */
    protected function executeRenewal(
        DeviceSubscription $subscription,
        $company,
        string $cardId
    ): void {
        DB::beginTransaction();

        try {
            // 1. Crear registro de pago pendiente
            $payment = Payment::createForRenewal($subscription);

            // 2. Procesar cargo en OpenPay
            $cycle = $subscription->billing_cycle === 'annual' ? 'Anual' : 'Mensual';
            $paymentResult = $this->paymentService->createCharge([
                'customer_id' => $company->openpay_customer_id,
                'card_id' => $cardId,
                'method' => 'card',
                'amount' => (float) $subscription->amount,
                'currency' => $subscription->currency ?? 'MXN',
                'description' => "Renovación {$cycle} GPS - IMEI {$subscription->device->imei}",
                'order_id' => "RENEWAL-{$subscription->id}-{$payment->id}",
            ]);

            if (!$paymentResult['success']) {
                $payment->markAsFailed(
                    $paymentResult['error_code'] ?? 'unknown',
                    $paymentResult['error'] ?? 'Error desconocido'
                );

                $this->handlePaymentFailure($subscription, $paymentResult['error'] ?? 'Error en pago');
                DB::commit();
                return;
            }

            // 3. Marcar pago como completado
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

            // 4. Actualizar suscripción
            $subscription->renew();

            Log::info("Renovación exitosa", [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'charge_id' => $paymentResult['charge_id'],
                'new_end_date' => $subscription->end_date,
            ]);

            // 5. Generar factura si está habilitado
            if ($this->invoiceService->canAutoInvoice($company)) {
                $invoiceResult = $this->invoiceService->generateRenewalInvoice(
                    $payment,
                    $subscription
                );

                if ($invoiceResult['success']) {
                    Log::info("Factura de renovación generada", [
                        'subscription_id' => $subscription->id,
                        'invoice_id' => $invoiceResult['invoice']->id,
                        'uuid' => $invoiceResult['uuid'],
                    ]);
                } else {
                    Log::warning("No se pudo generar factura de renovación", [
                        'subscription_id' => $subscription->id,
                        'error' => $invoiceResult['message'] ?? 'Error desconocido',
                    ]);
                }
            }

            DB::commit();
            $this->successful++;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Error en renovación", [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handlePaymentFailure($subscription, $e->getMessage());
        }
    }

    /**
     * Manejar fallo de pago
     */
    protected function handlePaymentFailure(DeviceSubscription $subscription, string $reason): void
    {
        $this->failed++;

        // Contar intentos fallidos (podrías tener un campo para esto)
        $failedAttempts = $subscription->metadata['failed_attempts'] ?? 0;
        $failedAttempts++;

        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'failed_attempts' => $failedAttempts,
                'last_failure_at' => now()->toIso8601String(),
                'last_failure_reason' => $reason,
            ]),
        ]);

        // Si hay muchos intentos fallidos, suspender
        if ($failedAttempts >= 3) {
            $subscription->update(['status' => 'suspended']);
            
            Log::warning("Suscripción suspendida por fallos de pago", [
                'subscription_id' => $subscription->id,
                'failed_attempts' => $failedAttempts,
            ]);

            // TODO: Enviar notificación al cliente
        } else {
            $subscription->update(['status' => 'payment_failed']);
        }

        Log::warning("Fallo en renovación", [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
            'failed_attempts' => $failedAttempts,
        ]);
    }

    /**
     * Marcar suscripción con fallo de pago
     */
    protected function markAsPaymentFailed(DeviceSubscription $subscription, string $reason): void
    {
        $this->failed++;
        
        $subscription->update([
            'status' => 'payment_failed',
            'metadata' => array_merge($subscription->metadata ?? [], [
                'last_failure_at' => now()->toIso8601String(),
                'last_failure_reason' => $reason,
            ]),
        ]);
    }

    /**
     * Obtener método de pago predeterminado de la empresa
     */
    protected function getDefaultPaymentMethod($company): ?string
    {
        // Opción 1: Si tienes una tabla de payment_methods
        // return $company->paymentMethods()->where('is_default', true)->first()?->openpay_card_id;

        // Opción 2: Obtener la primera tarjeta de OpenPay
        try {
            $cards = $this->paymentService->getCustomerCards($company->openpay_customer_id);
            return $cards[0]['id'] ?? null;
        } catch (\Exception $e) {
            Log::error("Error obteniendo tarjetas", [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Log para dry-run
     */
    protected function logDryRun(DeviceSubscription $subscription): void
    {
        Log::info("[DRY-RUN] Renovación simulada", [
            'subscription_id' => $subscription->id,
            'company' => $subscription->company->name,
            'amount' => $subscription->amount,
            'billing_cycle' => $subscription->billing_cycle,
        ]);
    }

    /**
     * Log para suscripción omitida
     */
    protected function logSkipped(DeviceSubscription $subscription, string $reason): void
    {
        $this->skipped++;

        Log::info("Suscripción omitida", [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Imprimir resumen
     */
    protected function printSummary(): void
    {
        $this->info('📊 Resumen de renovaciones:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Procesadas', $this->processed],
                ['Exitosas', $this->successful],
                ['Fallidas', $this->failed],
                ['Omitidas', $this->skipped],
            ]
        );

        if ($this->failed > 0) {
            $this->warn("⚠️  Hay {$this->failed} renovaciones fallidas. Revisa los logs.");
        }

        if ($this->successful > 0) {
            $this->info("✅ {$this->successful} renovaciones procesadas correctamente.");
        }
    }
}
