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
        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->date('date');
            // Tipos: FUEL, MAINTENANCE, REPAIR, INSURANCE, FINE, TOLL, OTHER
            $table->string('type');
            $table->decimal('amount', 10, 2); // Hasta 99 millones
            $table->string('description')->nullable(); // Ej: "Cambio de balatas"
            $table->integer('odometer')->nullable(); // Kilometraje al momento del gasto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
    }
};
