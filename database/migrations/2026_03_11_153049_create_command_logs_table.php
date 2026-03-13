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
        Schema::create('command_logs', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users'); // Quién lo envió (null si fue el Job)
            
            // Detalles del comando
            $table->string('command_type'); // engineStop, engineResume
            $table->string('action'); // stop, resume
            $table->text('reason')->nullable(); // Ej: "Pago de marzo no detectado"
            
            // Resultado de Traccar
            $table->boolean('status')->default(false); // Éxito o Fallo
            $table->text('error_message')->nullable();
            
            // Metadatos técnicos
            $table->json('metadata')->nullable(); // Para guardar la respuesta completa de Traccar
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_logs');
    }
};
