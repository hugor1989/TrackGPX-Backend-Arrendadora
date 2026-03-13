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
        Schema::create('lease_contracts', function (Blueprint $table) {
            $table->id();
            
            // Relación con la Arrendadora (Multitenancy)
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            // El Arrendatario (Tu modelo Account con rol customer)
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            
            // El Vehículo asociado
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            
            // Datos del Contrato
            $table->string('contract_number')->unique();
            $table->decimal('monthly_amount', 10, 2);
            $table->integer('payment_day'); // Día del mes para el cobro
            $table->integer('grace_days')->default(3); // Días de tolerancia antes del bloqueo
            
            // Control de Inmovilización
            $table->boolean('auto_immobilize')->default(true); // Si permite bloqueo automático por impago
            $table->boolean('is_immobilized')->default(false); // Estado actual del motor
            
            // Estado del Contrato
            $table->enum('status', ['active', 'past_due', 'legal_process', 'finished'])->default('active');
            
            $table->timestamps();
            
            // Índices para reportes rápidos
            $table->index(['company_id', 'status']);
            $table->index('payment_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_contracts');
    }
};
