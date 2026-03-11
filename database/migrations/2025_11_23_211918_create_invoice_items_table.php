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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            
            // Relación con factura
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('device_subscription_id')->nullable()->constrained('device_subscriptions')->nullOnDelete();
            
            // Tipo de concepto
            $table->enum('item_type', [
                'subscription',
                'service',
                'extra',
                'discount',
                'adjustment'
            ])->default('subscription');
            
            // Claves SAT para CFDI
            $table->string('sat_product_code', 10)->default('84111506')->comment('Clave producto SAT');
            $table->string('sat_unit_code', 10)->default('E48')->comment('Clave unidad SAT - Servicio');
            
            // Descripción y cantidades
            $table->text('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            
            // Cálculos
            $table->decimal('subtotal', 10, 2)->comment('quantity * unit_price');
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(16.00)->comment('Tasa de IVA en %');
            $table->decimal('tax_amount', 10, 2)->comment('Monto de IVA');
            $table->decimal('total', 10, 2)->comment('Total del item');
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index('invoice_id');
            $table->index('device_subscription_id');
            $table->index('item_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
