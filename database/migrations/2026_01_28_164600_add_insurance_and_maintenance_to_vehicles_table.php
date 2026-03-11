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
        Schema::table('vehicles', function (Blueprint $table) {
            // Campos para el Seguro
            $table->string('insurance_company')->nullable()->after('odometer');
            $table->string('policy_number')->nullable()->after('insurance_company');
            $table->date('policy_expiry')->nullable()->after('policy_number');
            $table->string('policy_document_url', 500)->nullable()->after('policy_expiry');

            // Campos para Mantenimiento
            $table->date('last_service_date')->nullable()->after('policy_document_url');
            $table->bigInteger('last_service_odometer')->nullable()->after('last_service_date');
            $table->integer('service_interval')->default(10000)->after('last_service_odometer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_company',
                'policy_number',
                'policy_expiry',
                'policy_document_url',
                'last_service_date',
                'last_service_odometer',
                'service_interval'
            ]);
        });
    }
};
