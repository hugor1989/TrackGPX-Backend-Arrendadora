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
        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();

            // Relación con device (imei o id, depende de tu estructura)
            $table->unsignedBigInteger('device_id');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');

            // Relación con geofence
            $table->unsignedBigInteger('geofence_id');
            $table->foreign('geofence_id')->references('id')->on('geofences')->onDelete('cascade');

            // Tipo de evento (enter / exit)
            $table->enum('event_type', ['enter', 'exit']);

            // Coordenadas donde ocurrió
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Fecha/hora reportada por el dispositivo
            $table->timestamp('event_time')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_events');
    }
};
