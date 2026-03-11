<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Position;
use Carbon\Carbon;

class MileageSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------- CONFIGURACIÓN ----------------
        $vehicleId = 6; // Tu Chevrolet JRV1138
        $date = Carbon::parse('2026-01-18'); // FECHA DIFERENTE (Domingo 18)
        // -----------------------------------------------

        $this->command->info("🛣️  Generando viaje de CARRETERA para Kilometraje el día {$date->toDateString()}...");

        // 1. Limpieza previa
        Position::where('vehicle_id', $vehicleId)
                ->whereDate('timestamp', $date)
                ->delete();

        // 2. Configuración de Ruta (Simulación CDMX -> Toluca -> CDMX)
        // Inicio: Centro CDMX
        $lat = 19.4326; 
        $lng = -99.1332;
        
        $currentTime = $date->copy()->setHour(9)->setMinute(0)->setSecond(0);
        $data = [];
        $fuelLevel = 90; // Empezamos con tanque casi lleno

        // --- FASE 1: SALIDA DE LA CIUDAD (Tráfico lento) ---
        // 30 mins, velocidad 20-40 km/h
        $this->command->warn("🚦 [09:00] Salida de CDMX (Tráfico)...");
        for ($i = 0; $i < 180; $i++) { // 180 puntos * 10 seg = 30 min
            $lat += 0.00015; // Moviéndose al oeste
            $lng -= 0.00015; 
            
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, rand(15, 40), $currentTime, $fuelLevel);
            $currentTime->addSeconds(10);
        }

        // --- FASE 2: AUTOPISTA (Alta Velocidad) ---
        // 1 hora, velocidad 80-110 km/h
        $this->command->warn("🏎️  [09:30] Autopista a Toluca (Alta Velocidad)...");
        for ($i = 0; $i < 360; $i++) { // 360 puntos * 10 seg = 60 min
            $lat += 0.00040; // Pasos más grandes = Mayor velocidad/distancia
            $lng -= 0.00060;
            
            $fuelLevel -= 0.02; // Consumo de gasolina
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, rand(85, 115), $currentTime, $fuelLevel);
            $currentTime->addSeconds(10);
        }

        // --- FASE 3: LLEGADA Y DESCANSO (Parada corta) ---
        // 30 mins detenido
        $this->command->warn("🛑 [10:30] Parada en destino...");
        for ($i = 0; $i < 180; $i++) { 
            // Lat/Lng congelados
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, 0, $currentTime, $fuelLevel);
            $currentTime->addSeconds(10);
        }

        // --- FASE 4: REGRESO A CDMX (Autopista) ---
        // 1 hora, velocidad constante 90 km/h
        $this->command->warn("🏎️  [11:00] Regreso a CDMX...");
        for ($i = 0; $i < 360; $i++) { 
            $lat -= 0.00040; // Regresando
            $lng += 0.00060;
            
            $fuelLevel -= 0.02;
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, rand(88, 105), $currentTime, $fuelLevel);
            $currentTime->addSeconds(10);
        }

        // Guardar en Bloques
        foreach (array_chunk($data, 500) as $chunk) {
            Position::insert($chunk);
        }

        $this->command->info("✅ Datos de Kilometraje insertados correctamente.");
    }

    private function generatePoint($vehicleId, $lat, $lng, $speed, $time, $fuel)
    {
        return [
            'device_id' => 1,
            'vehicle_id' => $vehicleId,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => $speed,
            'heading' => 270, // Rumbo oeste (simulado)
            'altitude' => 2240,
            'accuracy' => 10,
            'ignition' => $speed > 0 ? 1 : 0,
            'attributes' => json_encode([
                'fuel' => round($fuel, 2), 
                'odometer' => 15000 + rand(1, 100) // Simulación odómetro virtual
            ]),
            'address' => 'Carretera México-Toluca',
            'timestamp' => $time->copy(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}