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
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            
            // Relacionamos con la regla que se rompió (Nullable por si borras la regla, que quede el historial)
            $table->foreignId('alert_rule_id')->nullable()->constrained('alert_rules')->onDelete('set null');
            
            // Guardamos una "foto" del tipo y mensaje en ese momento
            // (Por si luego cambias el nombre de la regla, el log histórico no cambie)
            $table->string('type'); // ej. 'overspeed', 'power_cut'
            $table->string('message'); // ej. "Exceso de velocidad (110km/h)"
            
            // Datos del evento
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->float('speed')->nullable(); // Velocidad a la que iba
            
            // Estado de la alerta
            $table->timestamp('occurred_at'); // Cuándo pasó
            $table->boolean('is_read')->default(false); // ¿Ya la vio el monitorista?
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};
