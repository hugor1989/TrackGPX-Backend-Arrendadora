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
        Schema::table('lease_contracts', function (Blueprint $table) {
            // El enganche (lo que el cliente paga al inicio)
            $table->decimal('down_payment', 12, 2)->default(0)->after('monthly_amount');
            
            // El monto que realmente se está financiando (valor auto - enganche)
            $table->decimal('amount_financed', 12, 2)->default(0)->after('down_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->dropColumn(['down_payment', 'amount_financed']);
        });
    }
};
