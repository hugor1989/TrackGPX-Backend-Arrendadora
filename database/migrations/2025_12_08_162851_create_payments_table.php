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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('device_subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('device_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            
            // OpenPay
            $table->string('openpay_charge_id')->nullable()->unique();
            $table->string('openpay_order_id')->nullable();
            $table->string('authorization_code')->nullable();
            
            // Montos
            $table->decimal('amount', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MXN');
            
            // Información del pago
            $table->enum('type', ['activation', 'renewal', 'manual', 'adjustment', 'refund'])->default('manual');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['card', 'bank_account', 'store', 'cash', 'transfer', 'other'])->default('card');
            
            // Detalles
            $table->string('description')->nullable();
            $table->string('card_type')->nullable(); // visa, mastercard, amex
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            
            // Errores (si falla)
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            
            // Fechas
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('company_id');
            $table->index('device_subscription_id');
            $table->index('status');
            $table->index('type');
            $table->index('paid_at');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
