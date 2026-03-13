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
        Schema::create('customer_risk_scores', function (Blueprint $table) {
            $table->id();
            
            // Relación con la cuenta del cliente
            $table->foreignId('account_id')
                  ->constrained('accounts')
                  ->onDelete('cascade');
            
            // Calificación cualitativa (Bajo, Medio, Alto)
            $table->enum('score', ['Bajo', 'Medio', 'Alto'])->default('Bajo');
            
            // Puntos acumulados para el cálculo (0 a 100)
            $table->integer('points')->default(0);
            
            // Razones del score (ej: "3 atrasos, 1 intento de jammer")
            $table->text('reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_risk_scores');
    }
};
