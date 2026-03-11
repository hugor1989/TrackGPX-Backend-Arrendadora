<?php

namespace App\Http\Controllers\Api\DriverScore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\AlertLog;
use App\Models\Position;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DriverScoreController extends Controller
{
    public function getRanking(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->input('start_date', now()->startOfWeek()));
            $endDate = Carbon::parse($request->input('end_date', now()->endOfWeek()));
            $companyId = $request->user()->company_id;

            $vehicles = Vehicle::where('company_id', $companyId)
                ->with(['driver.account'])
                ->get();

            $ranking = [];

            foreach ($vehicles as $vehicle) {
                // Tu lógica de cálculo (resumida para no repetir todo el bloque)
                $alerts = AlertLog::where('vehicle_id', $vehicle->id)
                    ->whereBetween('occurred_at', [$startDate, $endDate])
                    ->get();

                $overspeed = $alerts->where('type', 'OVERSPEED')->count();
                $harshBrake = $alerts->where('type', 'HARSH_BRAKING')->count();
                $geofence = $alerts->where('type', 'GEOFENCE_EXIT')->count();

                $score = 100 - ($overspeed * 5) - ($harshBrake * 10) - ($geofence * 2);
                if ($score < 0) $score = 0;

                $driverName = $vehicle->driver->account->name 
                    ?? trim(($vehicle->driver->first_name ?? '') . ' ' . ($vehicle->driver->last_name ?? '')) 
                    ?? $vehicle->driver_name 
                    ?? 'Sin Asignar';

                $grade = 'F';
                if ($score >= 90) $grade = 'A';
                elseif ($score >= 80) $grade = 'B';
                elseif ($score >= 60) $grade = 'C';

                $ranking[] = [
                    'vehicle' => $vehicle->brand . ' ' . $vehicle->plate,
                    'driver' => $driverName,
                    'score' => $score,
                    'events' => [
                        'overspeed' => $overspeed,
                        'braking' => $harshBrake,
                        'geofence' => $geofence
                    ],
                    'grade' => $grade
                ];
            }

            usort($ranking, fn($a, $b) => $b['score'] <=> $a['score']);

            // --- CORRECCIÓN AQUÍ: Estructura estándar ---
            return response()->json([
                'success' => true,
                'count' => count($ranking),
                'data' => $ranking
            ], 200);
            // --------------------------------------------

        } catch (\Exception $e) {
            Log::error("Error en DriverScore: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular ranking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getGrade($score)
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 60) return 'C';
        return 'F';
    }
}