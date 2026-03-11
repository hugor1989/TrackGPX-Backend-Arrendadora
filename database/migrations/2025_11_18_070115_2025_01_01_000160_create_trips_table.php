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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();

            $table->decimal('distance_km', 10, 2)->default(0);
            $table->integer('duration_min')->default(0);
            $table->float('avg_speed')->default(0);
            $table->float('max_speed')->default(0);
            $table->integer('idle_time_min')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
