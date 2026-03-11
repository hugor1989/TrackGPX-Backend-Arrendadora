<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\DB;

class DashboardController extends AppBaseController
{
   public function index(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->company_id;

        // 1. DATA BASE
        $vehicles = Vehicle::where('company_id', $companyId)->get();

        // 2. KPIS
        $counters = [
            'total' => $vehicles->count(),
            'active' => $vehicles->where('status', 'active')->count(),
            'maintenance' => $vehicles->where('status', 'maintenance')->count(),
            'fleet_score' => 87, // Promedio general de calificación (simulado por ahora)
        ];

        // 3. CONDUCTORES RIESGOSOS (Lo que pediste: "Los peores")
        // En un futuro esto vendrá de telemetría real (frenones, excesos)
        $riskyDrivers = [
            ['name' => 'Roberto Gómez', 'vehicle' => 'Nissan NP300', 'score' => 45, 'incidents' => 12], // 12 incidentes
            ['name' => 'Luis Martínez', 'vehicle' => 'Aveo 2021', 'score' => 52, 'incidents' => 8],
            ['name' => 'Carlos Ruiz', 'vehicle' => 'Ford Ranger', 'score' => 60, 'incidents' => 5],
        ];

        // 4. SEMÁFORO LEGAL (Pólizas y Multas)
        // Buscamos vehículos cuya póliza venza en menos de 30 días
        $expiringDocs = [];
        foreach($vehicles as $v) {
            if($v->policy_expiry) {
                $days = Carbon::now()->diffInDays(Carbon::parse($v->policy_expiry), false);
                if($days < 30) {
                    $expiringDocs[] = [
                        'type' => 'Seguro',
                        'vehicle' => $v->name,
                        'days_left' => $days,
                        'status' => $days < 0 ? 'Vencido' : 'Por vencer'
                    ];
                }
            }
        }
        // Agregamos multas simuladas (ya que no tenemos tabla de multas conectada aún)
        $expiringDocs[] = ['type' => 'Multa', 'vehicle' => 'Kenworth T600', 'days_left' => -2, 'status' => 'Pago Vencido'];


        // 5. PRÓXIMOS MANTENIMIENTOS (Salud)
        // Simulamos los que están próximos según odómetro
        $upcomingMaintenance = [
            ['vehicle' => 'Versa 2022', 'service' => 'Cambio de Aceite', 'km_left' => 150],
            ['vehicle' => 'Nissan NP300', 'service' => 'Rotación Llantas', 'km_left' => 400],
            ['vehicle' => 'Moto Reparto 01', 'service' => 'Afinación', 'km_left' => -50], // Vencido
        ];

        return response()->json([
            'counters' => $counters,
            'risky_drivers' => $riskyDrivers,
            'legal_alerts' => $expiringDocs,
            'maintenance' => $upcomingMaintenance
        ]);
    }
}