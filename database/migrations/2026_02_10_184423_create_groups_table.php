<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear la tabla GROUPS
        Schema::create('groups', function (Blueprint $table) {
            $table->id();

            // Relación con la empresa (Multitenancy)
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            $table->string('name'); // Nombre del grupo
            $table->string('color')->default('#3b82f6'); // Color para el mapa

            // ✅ AQUÍ ESTÁ EL CAMBIO: Relación con la tabla 'accounts'
            // Si borras al usuario, el grupo se queda sin supervisor (set null) pero no se borra.
            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('accounts') // Apunta a tu tabla 'accounts'
                ->onDelete('set null');

            $table->timestamps();
        });

        // 2. Relación: Muchos carros ligados a un grupo
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('groups')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
        Schema::dropIfExists('groups');
    }
};
