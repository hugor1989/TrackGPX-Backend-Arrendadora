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
        Schema::table('positions', function (Blueprint $table) {
            // 1. Para sensores extras (batería, gasolina, puertas, odómetro virtual)
            $table->json('attributes')->nullable()->after('ignition');

            // 2. Datos GPS finos
            $table->double('altitude')->default(0)->after('longitude');
            $table->float('accuracy')->default(0)->after('heading'); // HDOP/Precisión en metros

            // 3. Dirección (Geo-decoding inverso, opcional pero útil para reportes rápidos)
            $table->string('address')->nullable()->after('attributes');

            // 4. EL SECRETO DE LA VELOCIDAD (Índice Compuesto)
            // Esto hace que las consultas de historial sean instantáneas
            $table->index(['vehicle_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // 1. Eliminamos el índice compuesto primero
            $table->dropIndex(['vehicle_id', 'timestamp']);

            // 2. Eliminamos las columnas que agregamos
            // Se pasa un array con los nombres de las columnas a borrar
            $table->dropColumn([
                'attributes',
                'altitude',
                'accuracy',
                'address'
            ]);
        });
    }
};
