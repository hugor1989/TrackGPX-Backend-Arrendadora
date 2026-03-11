<?php

namespace App\Http\Controllers\Api\History;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;
use Carbon\Carbon;

class HistoryController extends Controller
{
    public function getRoute(Request $request)
    {
        // Validamos qué nos pide el Frontend
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date' => 'required|date', // Ej: '2026-01-18'
            'start_time' => 'nullable|date_format:H:i', // Ej: '08:00' (Opcional)
            'end_time' => 'nullable|date_format:H:i',   // Ej: '20:00' (Opcional)
        ]);

        $vehicleId = $request->vehicle_id;
        $date = $request->date;

        // Definir rango de horas
        $start = $request->start_time ? Carbon::parse("$date $request->start_time") : Carbon::parse($date)->startOfDay();
        $end = $request->end_time ? Carbon::parse("$date $request->end_time") : Carbon::parse($date)->endOfDay();

        // CONSULTA OPTIMIZADA (Gracias al índice que creaste)
        // Seleccionamos solo lo necesario para pintar el mapa (ahorramos memoria)
        $positions = Position::where('vehicle_id', $vehicleId)
            ->whereBetween('timestamp', [$start, $end])
            ->orderBy('timestamp', 'asc')
            ->select([
                'id', 
                'latitude', 
                'longitude', 
                'speed', 
                'heading', 
                'ignition', 
                'timestamp', 
                'attributes',
                'address'
            ])
            // Límite de seguridad: Si hay 10,000 puntos, el navegador puede trabarse.
            // Para "Playback" usualmente traemos todo, pero ojo con rutas de 24 horas.
            ->limit(5000) 
            ->get();

        return response()->json([
            'success' => true,
            'count' => $positions->count(),
            'data' => $positions
        ]);
    }
}
