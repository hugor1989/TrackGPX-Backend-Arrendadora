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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            
            $table->string('name'); 

            // LISTA COMPLETA DE ALERTAS (Nivel Competitivo)
            $table->enum('type', [
                // Geocercas
                'geofence_enter',       
                'geofence_exit',        
                
                // Conducción
                'overspeed',            
                'stop_duration',        // Ralentí excesivo
                'harsh_acceleration',   
                'harsh_braking',        
                'harsh_turn',           
                
                // Seguridad / Hardware (Lo que necesitas)
                'power_cut',            // Corte de corriente
                'low_battery_vehicle',  
                'low_battery_device',   
                'sos_button',           
                'jamming',              // Inhibidor
                'towing',               // Grúa (movimiento sin ignición)
                'door_open',            
                
                // Uso
                'ignition_on',          
                'ignition_off',         
                'sensor_fuel_drop',     
                'sensor_temperature',   
                'maintenance_due'       
            ]);

            // Relación con geocercas (opcional)
            $table->foreignId('geofence_id')->nullable()->constrained('geofences')->onDelete('cascade');
            
            // Valores de umbral (km/h, grados, minutos, etc)
            $table->float('value')->nullable();
            
            // Configuraciones JSON
            $table->json('notification_settings')->nullable();
            $table->json('schedule_settings')->nullable(); 

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
