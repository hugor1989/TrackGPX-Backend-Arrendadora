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
        Schema::create('lease_payments', function (Blueprint $table) {
            $table->id();
            
            // Relación con el contrato
            $table->foreignId('lease_contract_id')
                  ->constrained('lease_contracts')
                  ->cascadeOnDelete();
            
            // Datos del pago
            $table->decimal('amount', 10, 2);
            $table->date('payment_date'); 
            $table->string('reference')->nullable(); // Folio de transferencia o depósito
            $table->string('month_paid', 7); // Formato 'YYYY-MM' para facilitar búsquedas del Job
            
            // Soporte documental
            $table->string('evidence_path')->nullable(); // Ruta de la foto del comprobante
            
            // Auditoría (Quién de la arrendadora capturó el pago)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            
            $table->timestamps();

            // Índice para que el Job de bloqueo encuentre los pagos rápido
            $table->index(['lease_contract_id', 'month_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_payments');
    }
};
