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
        Schema::create('invoice_requests', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->constrained('billing_cycles')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('billing_info_id')->constrained('company_billing_info')->restrictOnDelete();
            
            // Periodo solicitado
            $table->date('period_start');
            $table->date('period_end');
            
            // Montos
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MXN');
            
            // Estado de la solicitud
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending');
            
            // Resultado
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            
            // Información adicional
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            
            // Fechas importantes
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index(['company_id', 'status']);
            $table->index('billing_cycle_id');
            $table->index('status');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_requests');
    }
};
