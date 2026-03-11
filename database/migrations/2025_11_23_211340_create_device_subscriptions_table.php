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
        Schema::create('device_subscriptions', function (Blueprint $table) {
            $table->id();
            
            // Relaciones principales
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            
            // Precios y facturación
            $table->decimal('monthly_price', 10, 2);
            $table->string('currency', 3)->default('MXN');
            
            // Estado
            $table->enum('status', ['active', 'paused', 'canceled', 'pending'])->default('active');
            
            // Fechas importantes
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_billing_date');
            
            // Configuración
            $table->boolean('auto_renew')->default(true);
            
            // Control de pausas
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('pause_reason')->nullable();
            
            // Control de cancelaciones
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('cancelation_reason')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index(['company_id', 'status']);
            $table->index('device_id');
            $table->index('vehicle_id');
            $table->index(['next_billing_date', 'status']);
            $table->index('status');
            
            // Un dispositivo solo puede tener una suscripción activa
            $table->unique(['device_id', 'status'], 'unique_active_device_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_subscriptions');
    }
};
