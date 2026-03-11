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
        Schema::create('scraping_logs', function (Blueprint $table) {
            $table->id();
            
            // Relación con el vehículo (si borras el vehículo, se borran sus logs)
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            
            // Estado consultado (Ej: 'Jalisco', 'CDMX')
            $table->string('state');
            
            // Acción realizada (Ej: 'fines_check', 'tax_check')
            $table->string('action');
            
            // Resultado (Ej: 'success', 'error', 'success_with_data')
            $table->string('result');
            
            // Guardamos todo lo que respondió el scraper por si hay que auditar errores
            $table->json('raw_data')->nullable();
            
            // Fecha exacta de ejecución
            $table->timestamp('executed_at')->useCurrent();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraping_logs');
    }
};
