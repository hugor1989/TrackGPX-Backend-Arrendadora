<?php

namespace App\Http\Controllers\Api\Mileage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\Position;
use Carbon\Carbon;

class MileageController extends Controller
{
    public function getMileage(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'vehicle_id' => 'nullable|integer'
        ]);

        $companyId = $request->user()->company_id; 
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $vehiclesQuery = Vehicle::where('company_id', $companyId)
            ->with(['driver.account']);
            
        if ($request->filled('vehicle_id')) {
            $vehiclesQuery->where('id', $request->vehicle_id);
        }

        $vehicles = $vehiclesQuery->get();
        $reportData = [];

        foreach ($vehicles as $vehicle) {
            // Optimización: solo traemos las columnas necesarias
            $positions = Position::where('vehicle_id', $vehicle->id)
                ->whereBetween('timestamp', [$startDate, $endDate])
                ->orderBy('timestamp', 'asc')
                ->get(['latitude', 'longitude', 'speed', 'timestamp']);

            if ($positions->isEmpty()) {
                $reportData[] = $this->formatRow($vehicle, 0, 0, 0, 0, 0);
                continue;
            }

            $totalDistanceMeters = 0;
            $maxSpeed = 0;
            $avgSpeedSum = 0;
            $movingSeconds = 0;
            $stoppedSeconds = 0;

            for ($i = 0; $i < count($positions) - 1; $i++) {
                $p1 = $positions[$i];
                $p2 = $positions[$i+1];

                $dist = $this->calculateDistance($p1->latitude, $p1->longitude, $p2->latitude, $p2->longitude);
                $totalDistanceMeters += $dist;
                
                if ($p1->speed > $maxSpeed) $maxSpeed = $p1->speed;
                $avgSpeedSum += $p1->speed;

                $t1 = Carbon::parse($p1->timestamp);
                $t2 = Carbon::parse($p2->timestamp);
                $secondsDiff = $t2->diffInSeconds($t1);

                // Evitamos saltos temporales ilógicos (GPS apagado por horas)
                if ($secondsDiff < 600) { 
                    if ($p1->speed > 2) {
                        $movingSeconds += $secondsDiff;
                    } else {
                        $stoppedSeconds += $secondsDiff;
                    }
                }
            }

            $totalKm = round($totalDistanceMeters / 1000, 2);
            $avgSpeed = round($avgSpeedSum / count($positions), 1);

            $reportData[] = $this->formatRow($vehicle, $totalKm, $maxSpeed, $avgSpeed, $movingSeconds, $stoppedSeconds);
        }

        // Ordenar por distancia descendente
        usort($reportData, function($a, $b) {
            return $b['distance_km'] <=> $a['distance_km'];
        });

        return response()->json([
            'success' => true,
            'data' => $reportData
        ]);
    }

    private function formatRow($vehicle, $km, $maxSpeed, $avgSpeed, $movingSec, $stoppedSec)
    {
        $formatTime = function($seconds) {
            if ($seconds <= 0) return "0m";
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            return ($h > 0 ? "{$h}h " : "") . "{$m}m";
        };

        // Rendimiento: Si el vehículo tiene configurado km_per_liter en la BD, lo usamos
        // si no, usamos 8.0 por defecto.
        $efficiency = (float)($vehicle->fuel_efficiency ?? 8.0);
        $fuelLiters = $efficiency > 0 ? round($km / $efficiency, 2) : 0;

        $driverName = 'No Asignado';
        if ($vehicle->driver) {
             $driverName = $vehicle->driver->account->name 
                ?? trim(($vehicle->driver->first_name ?? '') . ' ' . ($vehicle->driver->last_name ?? ''));
        }

        return [
            'vehicle_id'    => $vehicle->id,
            'vehicle_name'  => ($vehicle->brand ?? 'Vehículo') . ' ' . $vehicle->plate,
            'driver_name'   => $driverName,
            'distance_km'   => (float)$km,
            'max_speed'     => (float)$maxSpeed,
            'avg_speed'     => (float)$avgSpeed,
            // IMPORTANTE: Enviamos el número puro para que React no falle
            'fuel_consumption' => (float)$fuelLiters, 
            'moving_time'   => $formatTime($movingSec),
            'stopped_time'  => $formatTime($stoppedSec),
            'efficiency_config' => $efficiency // Útil para mostrar km/L en el frontend
        ];
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}