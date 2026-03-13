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
        // 1. Limpiar la tabla de Vehículos
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });

        // 2. Ajustar la tabla de Asignaciones (Pivote)
        Schema::table('vehicle_assignments', function (Blueprint $table) {
            // Quitamos la relación vieja con drivers
            $table->dropForeign(['driver_id']);

            // Renombramos la columna a account_id
            $table->renameColumn('driver_id', 'account_id');

            // Creamos la nueva relación con la tabla accounts
            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios si algo sale mal
        Schema::table('vehicle_assignments', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->renameColumn('account_id', 'driver_id');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->after('company_id');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('set null');
        });
    }
};
