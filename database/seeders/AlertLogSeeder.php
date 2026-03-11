<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlertLog;
use App\Models\Vehicle;
use App\Models\AlertRule;
use App\Models\Company;
use Carbon\Carbon;

class AlertLogSeeder extends Seeder
{
    public function run(): void
    {
        // 👇 AQUÍ PONES EL ID DE TU EMPRESA (Ej. 41, 1, etc.)
        $targetCompanyId = 43; 

        // Buscamos esa empresa específica
        $company = Company::find($targetCompanyId);

        // Si no existe, avisamos y paramos para no romper nada
        if (!$company) {
            $this->command->error("❌ No se encontró la empresa con ID: $targetCompanyId");
            return;
        }

        $this->command->info("✅ Generando alertas para la empresa: " . $company->name . " (ID: $targetCompanyId)");

        // Obtenemos vehículos y reglas DE ESA EMPRESA
        $vehicles = Vehicle::where('company_id', $company->id)->get();
        $rules = AlertRule::where('company_id', $company->id)->get();

        if ($vehicles->count() === 0) {
            $this->command->warn('⚠️ La empresa ID ' . $targetCompanyId . ' no tiene vehículos. Agrega algunos primero.');
            return;
        }

        // Generamos 50 alertas falsas
        for ($i = 0; $i < 50; $i++) {
            $vehicle = $vehicles->random();
            // Si hay reglas, asignamos una al azar, si no, null
            $rule = $rules->isNotEmpty() ? $rules->random() : null;
            
            $types = ['overspeed', 'geofence_exit', 'power_cut', 'stop_duration', 'geofence_enter', 'jamming'];
            $type = $types[array_rand($types)];

            $message = '';
            $speed = 0;

            switch ($type) {
                case 'overspeed':
                    $speed = rand(110, 160);
                    $message = "Exceso de velocidad detectado: {$speed} km/h";
                    break;
                case 'geofence_exit':
                    $message = "Salió de la zona segura sin autorización";
                    $speed = rand(20, 60);
                    break;
                case 'power_cut':
                    $message = "¡Corte de energía detectado! Posible sabotaje.";
                    break;
                case 'stop_duration':
                    $message = "Vehículo detenido por más de 20 min con motor encendido.";
                    break;
                default:
                    $message = "Evento detectado: {$type}";
            }

            AlertLog::create([
                'company_id' => $company->id,
                'vehicle_id' => $vehicle->id,
                'alert_rule_id' => $rule ? $rule->id : null,
                'type' => $type,
                'message' => $message,
                // Coordenadas aleatorias (Centradas en CDMX, ajusta si quieres otra zona)
                'latitude' => 19.4326 + (rand(-50, 50) / 1000), 
                'longitude' => -99.1332 + (rand(-50, 50) / 1000),
                'speed' => $speed,
                // Fechas aleatorias en los últimos 3 días
                'occurred_at' => Carbon::now()->subMinutes(rand(1, 4320)), 
                'is_read' => rand(0, 1) === 1
            ]);
        }
        
        $this->command->info("🎉 ¡Listo! 50 alertas generadas para la empresa ID $targetCompanyId.");
    }
}