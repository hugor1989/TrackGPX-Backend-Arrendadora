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
        Schema::table('device_subscriptions', function (Blueprint $table) {
            // Agregar amount (precio de la suscripción)
            $table->decimal('amount', 10, 2)->after('plan_id');
            
            // Agregar billing_cycle (monthly/annual)
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly')->after('amount');
            
            // Índice para consultas frecuentes
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['device_subscriptions_billing_cycle_index']);
            $table->dropColumn(['amount', 'billing_cycle']);
        });
    }
};
