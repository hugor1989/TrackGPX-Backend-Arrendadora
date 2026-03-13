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
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            
            // Relación con la tabla accounts (rol customer)
            $table->foreignId('account_id')
                  ->constrained('accounts')
                  ->cascadeOnDelete();
            
            // Información Fiscal y Personal 
            $table->string('rfc', 13)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // Datos de Contacto Directo [cite: 52]
            $table->string('phone_primary')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->text('address_home')->nullable();
            $table->text('address_office')->nullable();

            // Contactos de Emergencia / Referencias [cite: 116]
            // Vital para localización cuando el cliente no responde
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            // Información Laboral (Opcional pero útil para cobranza)
            $table->string('job_title')->nullable();
            $table->string('company_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
