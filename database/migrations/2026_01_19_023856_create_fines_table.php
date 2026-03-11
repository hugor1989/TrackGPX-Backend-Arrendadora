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
        Schema::create('fines', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            // Datos de la Multa
            $table->string('source');      // Ej: 'Jalisco', 'CDMX'
            $table->string('reference');   // El Folio de la multa
            $table->text('description')->nullable(); // El motivo
            $table->decimal('amount', 12, 2); // Monto con centavos
            $table->string('status')->default('pending'); // 'pending', 'paid', 'canceled'

            // Fechas
            $table->timestamp('detected_at')->nullable(); // Fecha de la infracción
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
