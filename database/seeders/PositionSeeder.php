<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vehicle;
use App\Models\Position; 
use Carbon\Carbon;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        // 👇 CONFIGURACIÓN MANUAL
        $targetCompanyId = 43;
        $targetVehicleId = 6;

        // 1. Buscamos el vehículo ESPECÍFICO
        $vehicle = Vehicle::where('id', $targetVehicleId)
                          ->where('company_id', $targetCompanyId)
                          ->first();

        if (!$vehicle) {
            $this->command->error("❌ Error: No se encontró el vehículo ID $targetVehicleId asociado a la empresa ID $targetCompanyId.");
            return;
        }

        // 2. Definir una Ruta Simulada (Ej: Un viaje corto en CDMX)
        // Coordenadas iniciales (Zócalo CDMX aprox)
        $lat = 19.4326; 
        $lng = -99.1332;
        
        // Empezamos AYER a las 8:00 AM
        $startTime = Carbon::yesterday()->setHour(8)->setMinute(0); 

        $this->command->info("📍 Generando ruta para: {$vehicle->brand} {$vehicle->model} (ID: {$vehicle->id})");

        $data = [];
        // Generar 2 horas de recorrido (720 puntos, uno cada 10 seg)
        // Aumenté a 720 puntos para que se vea más nutrido el mapa
        for ($i = 0; $i < 720; $i++) {
            
            // Simular movimiento "orgánico" (sin saltos locos)
            $lat += 0.00015 + (rand(-5, 15) / 100000); 
            $lng += 0.00015 + (rand(-15, 5) / 100000);
            
            // Simular datos realistas
            $speed = rand(20, 90); // km/h
            $heading = rand(0, 360);
            $ignition = $speed > 0 ? 1 : 0; // Si se mueve, motor encendido
            
            // JSON de atributos (Sensores simulados)
            $attributes = json_encode([
                'batteryLevel' => rand(90, 100),
                'fuel' => rand(40, 60), // Va bajando la gasolina imaginariamente
                'temp' => rand(85, 92),
                'odometer' => 15000 + ($i * 0.1) // Sumando km
            ]);

            $data[] = [
                'device_id' => 1, // Puedes ajustar esto si tienes un device_id real, si no 1 está bien para probar
                'vehicle_id' => $vehicle->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $speed,
                'heading' => $heading,
                'altitude' => 2250, // Altura CDMX aprox
                'accuracy' => 10,
                'ignition' => $ignition,
                'attributes' => $attributes,
                'address' => 'Simulated Street CDMX',
                'timestamp' => $startTime->copy()->addSeconds($i * 10), // Avanza 10 seg en cada vuelta
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insertar en lotes de 100 para no saturar memoria
            if (count($data) >= 100) {
                Position::insert($data);
                $data = [];
            }
        }
        
        // Insertar los restantes
        if (!empty($data)) Position::insert($data);
        
        $this->command->info("✅ ¡Listo! Ruta generada para el Vehículo 6 (Empresa 43).");
    }
}