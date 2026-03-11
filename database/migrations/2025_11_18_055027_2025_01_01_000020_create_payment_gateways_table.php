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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['stripe', 'openpay', 'paypal', 'mercado_pago']);
            $table->enum('mode', ['sandbox', 'production'])->default('sandbox');
            $table->string('account_id')->nullable();
            $table->string('public_key')->nullable();
            $table->string('last4')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
