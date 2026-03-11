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
        Schema::create('alert_rule_vehicle', function (Blueprint $table) {
            $table->id();
            
            // Definimos las columnas
            $table->unsignedBigInteger('alert_rule_id');
            $table->unsignedBigInteger('vehicle_id');
            
            // Definimos las llaves foráneas
            $table->foreign('alert_rule_id')->references('id')->on('alert_rules')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');

            $table->timestamps();

            // Evitar duplicados (Un vehículo no puede tener la misma regla 2 veces)
            $table->unique(['alert_rule_id', 'vehicle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rule_vehicle');
    }
};
