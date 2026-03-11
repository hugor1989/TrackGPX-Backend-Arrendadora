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
        Schema::table('invoices', function (Blueprint $table) {
             $table->string('folio', 20)
                ->nullable()
                ->after('invoice_number')
                ->comment('Folio CFDI consecutivo (TGX0000001...)');
            
            $table->string('serie', 5)
                ->default('A')
                ->after('folio')
                ->comment('Serie del CFDI (A, B, C...)');
            
            $table->index('folio', 'idx_invoices_folio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_folio');
            $table->dropColumn(['folio', 'serie']);
        });
    }
};
