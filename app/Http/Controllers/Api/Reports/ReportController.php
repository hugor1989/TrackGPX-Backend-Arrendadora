<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Vehicle;

use Carbon\Carbon;

class ReportController extends Controller
{
   public function getStops(Request $request)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date' => 'required|date',
            'min_minutes' => 'integer|min:1|max:120'
        ]);

        $vehicleId = $request->vehicle_id;
        $date = $request->date;
        $minDuration = $request->input('min_minutes', 5);

        // --- NUEVO: Obtenemos info del Vehículo y su Chofer Activo ---
        // Usamos with('driver') aprovechando la relación que ya tienes en tu Modelo
        $vehicle = Vehicle::with('driver')->find($vehicleId);

        $driverName = 'No Asignado';
        if ($vehicle && $vehicle->driver) {
            // Concatenamos nombre y apellido (ajusta si tus campos se llaman diferente)
            $driverName = trim($vehicle->driver->first_name . ' ' . $vehicle->driver->last_name);
        }
        // -------------------------------------------------------------

        // 1. Obtenemos posiciones (Igual que antes)
        $positions = Position::where('vehicle_id', $vehicleId)
            ->whereDate('timestamp', $date)
            ->orderBy('timestamp', 'asc')
            ->get(['latitude', 'longitude', 'speed', 'timestamp', 'address']);

        $stops = [];
        $potentialStopStart = null;
        $lastPosition = null;

        // 2. ALGORITMO (Igual que antes)
        foreach ($positions as $pos) {
            $isStopped = $pos->speed < 3;

            if ($isStopped) {
                if (!$potentialStopStart) $potentialStopStart = $pos;
            } else {
                if ($potentialStopStart) {
                    $this->processStop($stops, $potentialStopStart, $lastPosition, $minDuration);
                    $potentialStopStart = null;
                }
            }
            $lastPosition = $pos;
        }

        if ($potentialStopStart && $lastPosition) {
            $this->processStop($stops, $potentialStopStart, $lastPosition, $minDuration);
        }

        // --- RETORNO ACTUALIZADO ---
        return response()->json([
            'success' => true,
            'meta' => [ // Metadatos del reporte
                'vehicle' => $vehicle->brand . ' ' . $vehicle->plate,
                'driver' => $driverName, // <--- AQUÍ VA EL CHOFER
                'date' => $date
            ],
            'count' => count($stops),
            'data' => $stops
        ]);
    }

    // Helper para guardar la parada si cumple el tiempo mínimo
    private function processStop(&$stops, $startPos, $endPos, $minDuration)
    {
        $startTime = Carbon::parse($startPos->timestamp);
        $endTime = Carbon::parse($endPos->timestamp);
        $durationInMinutes = $startTime->diffInMinutes($endTime);

        if ($durationInMinutes >= $minDuration) {
            $stops[] = [
                'id' => uniqid(), // ID temporal para el frontend
                'latitude' => $startPos->latitude,
                'longitude' => $startPos->longitude,
                'start_time' => $startPos->timestamp->format('H:i:s'),
                'end_time' => $endPos->timestamp->format('H:i:s'),
                'duration' => $this->formatDuration($durationInMinutes),
                'address' => $startPos->address ?? 'Ubicación Desconocida' // Si tuvieras geocoding
            ];
        }
    }

    private function formatDuration($minutes)
    {
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return ($h > 0 ? "{$h}h " : "") . "{$m}min";
    }
}