<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Información del dispositivo
            $table->string('manufacturer')->nullable()->after('model');
            $table->string('protocol')->default('JT808')->after('manufacturer');
            
            // Sistema de activación
            $table->string('activation_code', 10)->unique()->after('imei');
            $table->timestamp('activated_at')->nullable()->after('activation_code');
            
            // Configuración y parámetros
            $table->json('config_parameters')->nullable()->after('protocol');
            $table->timestamp('last_connection')->nullable()->after('config_parameters');
            
            // Actualizar enum de status
            DB::statement("ALTER TABLE devices MODIFY COLUMN status ENUM('available', 'active', 'inactive', 'suspended', 'maintenance') DEFAULT 'available'");
            
            // Índices adicionales
            $table->index('activation_code');
            $table->index('status');
            $table->index('last_connection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['devices_activation_code_index']);
            $table->dropIndex(['devices_status_index']);
            $table->dropIndex(['devices_last_connection_index']);
            
            $table->dropColumn([
                'manufacturer',
                'protocol',
                'activation_code',
                'activated_at',
                'config_parameters',
                'last_connection',
            ]);
        });
    }
};
