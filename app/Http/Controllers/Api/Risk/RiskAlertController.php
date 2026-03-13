<?php

namespace App\Http\Controllers\Api\Risk;

use App\Http\Controllers\Controller;
use App\Models\AlertLog;
use App\Models\LeaseContract;
use App\Models\Vehicle;
use App\Models\CommandLog; // Importación añadida
use App\Services\TraccarCommandService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RiskAlertController extends Controller
{
    // Inyectamos el servicio de Traccar
    public function __construct(private TraccarCommandService $traccar) {}

    /**
     * Reporte de alertas críticas (Sabotaje, Fronteras, Jamming) [cite: 102, 121, 291]
     */
    public function getHighPriorityAlerts(Request $request)
    {
        $companyId = $request->user()->company_id;

        return response()->json([
            'status' => 'success',
            'data' => AlertLog::where('company_id', $companyId)
                ->whereHas('alertRule', function ($query) {
                    $query->where('priority', 'high');
                })
                ->with([
                    'vehicle:id,plate,model,brand',
                    'alertRule:id,name,priority,type'
                ])
                ->where('created_at', '>=', Carbon::now()->subDays(2))
                ->orderBy('created_at', 'desc')
                ->paginate(20)
        ]);
    }

    /**
     * Dashboard de Riesgo: Salud de la cartera [cite: 12, 172, 250]
     */
    public function getRiskSummary(Request $request)
    {
        $companyId = $request->user()->company_id;

        $pastDueCount = LeaseContract::where('company_id', $companyId)
            ->where('status', 'past_due')
            ->count();

        // Alertas de sabotaje según el documento [cite: 104, 105, 109, 291]
        $sabotageToday = AlertLog::where('company_id', $companyId)
            ->whereIn('type', ['power_cut', 'jamming', 'low_battery_device'])
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Violación de geocercas críticas [cite: 81, 280]
        $geofenceViolations = AlertLog::where('company_id', $companyId)
            ->whereHas('alertRule.geofence', function ($query) {
                $query->whereIn('category', ['danger', 'border']);
            })
            ->whereDate('created_at', Carbon::today())
            ->count();

        return response()->json([
            'status' => 'success',
            'summary' => [
                'total_past_due' => $pastDueCount,
                'sabotage_attempts_today' => $sabotageToday,
                'geofence_violations_today' => $geofenceViolations,
                'critical_risk_level' => ($sabotageToday > 0 || $geofenceViolations > 0) ? 'High' : 'Normal'
            ]
        ]);
    }

    /**
     * Modo Recuperación Manual (10s vs 5min) [cite: 233]
     */
    public function toggleRecoveryMode(Request $request, $vehicleId)
    {
        $request->validate(['active' => 'required|boolean']);

        $vehicle = Vehicle::where('company_id', $request->user()->company_id)
            ->with('device')
            ->findOrFail($vehicleId);

        if (!$vehicle->device) {
            return response()->json(['status' => 'error', 'message' => 'El vehículo no tiene un GPS asociado'], 422);
        }

        // Ajustamos al nombre correcto del método en tu TraccarCommandService
        $seconds = $request->active ? 10 : 180;
        $result = $this->traccar->setRecoveryFrequency($vehicle->device->traccar_id, $seconds);

        CommandLog::create([
            'vehicle_id'   => $vehicle->id,
            'user_id'      => $request->user()->id,
            'command_type' => 'custom',
            'action'       => $request->active ? 'ACTIVAR_RECUPERACION' : 'DESACTIVAR_RECUPERACION',
            'reason'       => $request->active ? 'Iniciado por usuario' : 'Recuperación finalizada',
            'status'       => $result['success'] ? 'success' : 'failed'
        ]);

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $request->active ? 'Comando enviado: Rastreo cada 10s' : 'Comando enviado: Rastreo normal'
        ]);
    }

    /**
     * Reporte de Unidades Sin Señal [cite: 118, 120]
     */
    public function getOfflineUnitsReport(Request $request)
    {
        $companyId = $request->user()->company_id;
        $threshold = now()->subHours(24); // Umbral de 24 horas [cite: 119]

        $offlineUnits = LeaseContract::where('company_id', $companyId)
            ->with([
                'account:id,name',
                'vehicle:id,plate,model,brand,device_id',
                'vehicle.device:id,last_update,last_lat,last_lng'
            ])
            ->whereHas('vehicle.device', function ($query) use ($threshold) {
                $query->where('last_update', '<', $threshold)
                    ->orWhereNull('last_update');
            })
            ->get()
            ->map(function ($contract) {
                return [
                    'cliente' => $contract->account->name ?? 'N/D',
                    'vehiculo' => "{$contract->vehicle->brand} {$contract->vehicle->model}",
                    'placa' => $contract->vehicle->plate,
                    'ultima_conexion' => $contract->vehicle->device->last_update ?? 'Nunca',
                    'ultima_ubicacion' => [
                        'lat' => (float) $contract->vehicle->device->last_lat,
                        'lng' => (float) $contract->vehicle->device->last_lng,
                    ],
                    'dias_desconectado' => $contract->vehicle->device->last_update
                        ? now()->diffInDays($contract->vehicle->device->last_update)
                        : 'Indefinido'
                ];
            });

        return response()->json([
            'status' => 'success',
            'count' => $offlineUnits->count(),
            'data' => $offlineUnits
        ]);
    }

    /**
     * Calcula el Score de Riesgo basado en el comportamiento del cliente.
     * Fuente: Documento Track GPX #1 Info Avanzada.
     */
    public function calculateCustomerScore($accountId)
    {
        $points = 0;
        $reasons = [];

        $contract = LeaseContract::where('account_id', $accountId)->first();
        if (!$contract) return;

        // 1. Criterio: Atrasos de pago [cite: 143]
        if ($contract->status === 'past_due') {
            $points += 40;
            $reasons[] = "Contrato actualmente en mora.";
        }

        // 2. Criterio: Sabotajes (Power cut, Jamming) [cite: 145]
        $sabotageCount = AlertLog::where('vehicle_id', $contract->vehicle_id)
            ->whereIn('type', ['power_cut', 'jamming'])
            ->where('created_at', '>=', now()->subMonths(3))
            ->count();

        if ($sabotageCount > 0) {
            $points += ($sabotageCount * 20);
            $reasons[] = "Se detectaron {$sabotageCount} intentos de sabotaje recientemente.";
        }

        // 3. Criterio: Zonas de movimiento (Geocercas de peligro) [cite: 144]
        $dangerZoneCount = AlertLog::where('vehicle_id', $contract->vehicle_id)
            ->whereHas('alertRule.geofence', function ($q) {
                $q->whereIn('category', ['danger', 'border']);
            })->count();

        if ($dangerZoneCount > 0) {
            $points += 30;
            $reasons[] = "Ingreso frecuente a zonas de alto riesgo o fronteras.";
        }

        // Determinamos el Score final [cite: 138, 139, 140, 141]
        $scoreName = 'Bajo';
        if ($points >= 70) $scoreName = 'Alto';
        elseif ($points >= 30) $scoreName = 'Medio';

        \App\Models\CustomerRiskScore::updateOrCreate(
            ['account_id' => $accountId],
            [
                'score' => $scoreName,
                'points' => $points,
                'reason' => implode(' ', $reasons)
            ]
        );
    }

    /**
     * Reporte de Score de Riesgo por Cliente.
     */
    public function getRiskScoreReport(Request $request)
    {
        $companyId = $request->user()->company_id;

        $report = \App\Models\CustomerRiskScore::whereHas('account', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
            ->with('account:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'cliente' => $item->account->name,
                    'score' => $item->score, // Bajo, Medio, Alto [cite: 138]
                    'motivo' => $item->reason
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $report
        ]);
    }

    /**
     * Obtiene las coordenadas de incidentes para el Heatmap.
     * Filtra por alertas de sabotaje y zonas prohibidas.
     */
    public function getIncidentHeatmap(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Buscamos incidentes de los últimos 6 meses para tener un mapa denso
        $incidents = AlertLog::where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereIn('type', ['power_cut', 'jamming', 'towing'])
                    ->orWhereHas('alertRule.geofence', function ($q) {
                        $q->whereIn('category', ['danger', 'border']);
                    });
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('latitude', 'longitude', 'type', 'created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $incidents
        ]);
    }

    /**
     * Estadísticas de las geocercas más "calientes".
     * Sirve para decir: "La zona de Lázaro Cárdenas tiene 15 incidentes".
     */
    public function getDangerZoneStats(Request $request)
    {
        $companyId = $request->user()->company_id;

        $stats = AlertLog::where('company_id', $companyId)
            ->whereHas('alertRule.geofence', function ($q) {
                $q->where('category', 'danger');
            })
            ->with('alertRule.geofence:id,name')
            ->get()
            ->groupBy('alertRule.geofence.name')
            ->map(fn($group) => $group->count());

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}
