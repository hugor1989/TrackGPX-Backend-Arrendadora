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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            // Ej: "Cambio de Aceite", "Rotación de Llantas", "Seguro"
            $table->string('name');

            // Configuración de frecuencia (puede ser por km, por días o ambos)
            $table->integer('interval_km')->nullable(); // Ej: 10000
            $table->integer('interval_days')->nullable(); // Ej: 180 (6 meses)

            // Estado actual ("La última vez que se hizo")
            $table->bigInteger('last_service_odometer')->default(0);
            $table->date('last_service_date')->nullable();

            // Control
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_driver')->default(true);
            $table->boolean('notify_supervisor')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
