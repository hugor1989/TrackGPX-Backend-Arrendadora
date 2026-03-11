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
       Schema::table('plans', function (Blueprint $table) {
            // Campos de OpenPay
            $table->string('openpay_plan_id')->nullable()->unique()->after('id');
            
            // Cambiar price_monthly a price genérico
            $table->renameColumn('price_monthly', 'price');
            
            // Agregar campos de frecuencia
            $table->string('currency', 3)->default('MXN')->after('price');
            $table->enum('interval', ['day', 'week', 'month', 'year'])->default('month')->after('currency');
            $table->integer('interval_count')->default(1)->after('interval');
            
            // Características en JSON
            $table->json('features')->nullable()->after('description');
            
            // Estado del plan
            $table->enum('status', ['active', 'inactive'])->default('active')->after('features');
            
            // Código SAT para facturación CFDI
            $table->string('sat_product_code', 20)->default('81112001')->after('status');
            
            // Índices
            $table->index('openpay_plan_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['plans_openpay_plan_id_index']);
            $table->dropIndex(['plans_status_index']);
            
            $table->dropColumn([
                'openpay_plan_id',
                'currency',
                'interval',
                'interval_count',
                'features',
                'status',
                'sat_product_code'
            ]);
            
            $table->renameColumn('price', 'price_monthly');
        });
    }
};
