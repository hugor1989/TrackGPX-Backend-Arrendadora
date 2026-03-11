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
        Schema::create('invoices', function (Blueprint $table) {
             $table->id();
            
            // Relaciones
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade');
            
            $table->unsignedBigInteger('billing_cycle_id')->nullable();
            $table->index('billing_cycle_id');
            
            // Información básica de factura
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date');
            
            // Montos
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MXN');
            
            // === CAMPOS CFDI 4.0 ===
            $table->string('cfdi_uuid', 36)->nullable()->unique();
            $table->string('cfdi_folio')->nullable();
            $table->string('cfdi_serie')->nullable();
            $table->text('cfdi_xml_path')->nullable();
            $table->text('cfdi_pdf_path')->nullable();
            $table->text('cfdi_original_string')->nullable();
            $table->text('cfdi_sat_seal')->nullable();
            $table->text('cfdi_cfdi_seal')->nullable();
            $table->string('cfdi_sat_cert_number')->nullable();
            $table->timestamp('cfdi_stamp_date')->nullable();
            
            // Información del PAC
            $table->string('pac_name')->nullable();
            $table->string('pac_rfc')->nullable();
            
            // Datos del emisor
            $table->string('issuer_rfc', 13)->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_fiscal_regime')->nullable();
            
            // Datos del receptor
            $table->string('receiver_rfc', 13)->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_fiscal_regime')->nullable();
            $table->string('receiver_zip_code', 5)->nullable();
            $table->string('receiver_tax_regime')->nullable();
            
            // Uso CFDI
            $table->string('cfdi_use')->nullable();
            
            // Método y forma de pago CFDI
            $table->string('cfdi_payment_method')->nullable();
            $table->string('cfdi_payment_form')->nullable();
            
            // Información de exportación
            $table->string('export_type')->default('01');
            
            // Campos de cancelación
            $table->timestamp('cfdi_canceled_at')->nullable();
            $table->string('cfdi_cancellation_status')->nullable();
            $table->text('cfdi_cancellation_reason')->nullable();
            
            // Estado
            $table->enum('status', [
                'draft',
                'pending',
                'issued',
                'paid',
                'canceled',
                'refunded'
            ])->default('draft');
            
            // Información de pago
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('payment_reference')->nullable();
            
            // Notas
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('invoice_date');
            $table->index('status');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date']);
            $table->index('cfdi_folio');
            $table->index(['cfdi_serie', 'cfdi_folio']);
            $table->index('issuer_rfc');
            $table->index('receiver_rfc');
            $table->index('cfdi_stamp_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
