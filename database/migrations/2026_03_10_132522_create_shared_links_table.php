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
        Schema::create('shared_links', function (Blueprint $table) {
            $table->id();
            // Relación directa con tu tabla de vehículos de Laravel
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');

            // El token que irá en la URL (ej: trackgpx.com/share/abc-123-uuid)
            $table->string('token')->unique();

            // Seguridad y Control
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->string('password')->nullable(); // Por si Patricio quiere links con clave
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_links');
    }
};
