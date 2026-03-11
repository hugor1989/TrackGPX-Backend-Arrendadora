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
        Schema::create('company_users', function (Blueprint $table) {
            $table->id(); // BIGINT id PK autoincremental
            $table->foreignId('account_id')->constrained()->onDelete('cascade'); // BIGINT account_id FK
            $table->foreignId('company_id')->constrained()->onDelete('cascade'); // BIGINT company_id FK
            $table->string('name'); // VARCHAR name
            $table->string('phone')->nullable(); // VARCHAR phone (nullable por si no siempre tiene teléfono)
            $table->string('position')->nullable(); // VARCHAR position
            $table->string('timezone')->default('UTC'); // VARCHAR timezone con valor por defecto
            $table->timestamps(); // created_at y updated_at
            
            // Opcional: índices adicionales para mejor rendimiento
            $table->index('account_id');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_users');
    }
};
