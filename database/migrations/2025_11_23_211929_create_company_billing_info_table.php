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
        Schema::create('company_billing_info', function (Blueprint $table) {
            $table->id();
            
            // Relación con empresa (uno a uno)
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            
            // Datos fiscales básicos
            $table->string('rfc', 13);
            $table->string('legal_name', 255);
            $table->string('tax_regime', 10)->comment('601, 603, 612, etc');
            
            // Domicilio fiscal
            $table->string('postal_code', 10);
            $table->string('street', 255)->nullable();
            $table->string('exterior_number', 20)->nullable();
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 3)->default('MEX');
            
            // Configuración de facturación por defecto
            $table->string('cfdi_use', 3)->default('G03')->comment('Uso de CFDI por defecto');
            $table->string('payment_form', 3)->default('PUE')->comment('Forma de pago por defecto');
            $table->string('payment_method', 3)->default('99')->comment('Método de pago por defecto');
            
            // Contacto
            $table->string('email_for_invoices', 255)->nullable();
            $table->string('phone', 20)->nullable();
            
            // Configuración automática
            $table->boolean('auto_request_invoice')->default(false)->comment('Solicitar factura automáticamente cada mes');
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index('rfc');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_billing_info');
    }
};
