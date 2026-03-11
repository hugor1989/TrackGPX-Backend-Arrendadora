<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            
            // Relación con empresa
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            // Periodo
            $table->date('period_start');
            $table->date('period_end');
            
            // Columnas calculadas para mes y año (para facilitar consultas)
            $table->integer('billing_month')->nullable()->storedAs('MONTH(period_end)');
            $table->integer('billing_year')->nullable()->storedAs('YEAR(period_end)');
            
            // Métricas
            $table->integer('total_devices')->default(0);
            $table->integer('active_days')->nullable();
            
            // Montos
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('MXN');
            
            // Estado del cobro
            $table->enum('status', [
                'pending',
                'processing',
                'charged',
                'failed',
                'refunded',
                'waived'
            ])->default('pending');
            
            // Información de pago de OpenPay (sin foreign key)
            $table->string('openpay_customer_id')->nullable()->comment('ID del customer en OpenPay');
            $table->string('openpay_card_id')->nullable()->comment('ID de la tarjeta usada en OpenPay');
            $table->string('openpay_transaction_id')->nullable()->comment('ID de transacción de OpenPay');
            $table->string('openpay_charge_id')->nullable()->comment('ID del cargo en OpenPay');
            $table->timestamp('charged_at')->nullable();
            
            // Relación con factura
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->boolean('invoice_requested')->default(false);
            
            // Control de reintentos
            $table->integer('charge_attempt_count')->default(0);
            $table->text('last_charge_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index(['company_id', 'period_start', 'period_end']);
            $table->index(['billing_year', 'billing_month']);
            $table->index('status');
            $table->index(['next_retry_at', 'status']);
            $table->index('openpay_customer_id');
            $table->index('openpay_transaction_id');
            
            // Constraint único: un ciclo por empresa/periodo
            $table->unique(['company_id', 'period_start', 'period_end'], 'unique_company_billing_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
