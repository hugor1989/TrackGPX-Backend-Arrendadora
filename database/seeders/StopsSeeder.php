<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vehicle;
use App\Models\Position;
use Carbon\Carbon;

class StopsSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------- CONFIGURACIÓN ----------------
        $vehicleId = 6;      // Tu Chevrolet JRV1138
        // FECHA: 16 de Enero (Para no encimar con tu demo del 17)
        $date = Carbon::parse('2026-01-16'); 
        // -----------------------------------------------

        $this->command->info("🛑 Generando escenario de PARADAS para el día {$date->toDateString()}...");

        // 1. Limpiamos datos anteriores SOLO de ese día y ese vehículo
        Position::where('vehicle_id', $vehicleId)
                ->whereDate('timestamp', $date)
                ->delete();

        // 2. Coordenadas Iniciales (Un punto diferente, ej. Polanco CDMX)
        $lat = 19.4350; 
        $lng = -99.1950;
        
        // Arrancamos a las 7:00 AM
        $currentTime = $date->copy()->setHour(7)->setMinute(0)->setSecond(0);
        
        $data = [];

        // --- FASE 1: RUTA MATUTINA (7:00 - 8:30) ---
        // 90 min manejando
        $this->command->warn("📍 [07:00] Iniciando ruta...");
        for ($i = 0; $i < 540; $i++) { 
            $lat += 0.00010; 
            $lng += 0.00010; 
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, rand(30, 60), $currentTime);
            $currentTime->addSeconds(10);
        }

        // --- FASE 2: PARADA LARGA EN ALMACÉN (08:30 - 10:00) ---
        // 90 min DETENIDO
        $this->command->warn("🛑 [08:30] Parada en Almacén (90 min)...");
        for ($i = 0; $i < 540; $i++) { 
            // Lat/Lng congeladas, velocidad 0
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, 0, $currentTime);
            $currentTime->addSeconds(10);
        }

        // --- FASE 3: TRASLADO CORTO (10:00 - 10:20) ---
        // 20 min manejando
        $this->command->warn("📍 [10:00] Traslado a oficinas...");
        for ($i = 0; $i < 120; $i++) { 
            $lat -= 0.00020; 
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, rand(20, 50), $currentTime);
            $currentTime->addSeconds(10);
        }

        // --- FASE 4: PARADA TRÁMITE (10:20 - 10:50) ---
        // 30 min DETENIDO
        $this->command->warn("🛑 [10:20] Parada Trámite (30 min)...");
        for ($i = 0; $i < 180; $i++) { 
            $data[] = $this->generatePoint($vehicleId, $lat, $lng, 0, $currentTime);
            $currentTime->addSeconds(10);
        }

        // Insertar en la BD
        foreach (array_chunk($data, 500) as $chunk) {
            Position::insert($chunk);
        }

        $this->command->info("✅ Escenario de Paradas creado exitosamente para el 16-Ene.");
    }

    private function generatePoint($vehicleId, $lat, $lng, $speed, $time)
    {
        return [
            'device_id' => 1,
            'vehicle_id' => $vehicleId,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => $speed,
            'heading' => 0,
            'altitude' => 2240,
            'accuracy' => 10,
            'ignition' => $speed > 0 ? 1 : 0,
            'attributes' => json_encode(['fuel' => 70, 'battery' => 98]),
            'address' => 'Ubicación Test CDMX',
            'timestamp' => $time->copy(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}