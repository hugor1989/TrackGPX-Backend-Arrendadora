<?php

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LeaseContract;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SummaryController extends Controller
{
    public function getDashboardSummary(Request $request)
    {
        $companyId = $request->user()->company_id;

        // --- 1. ESTADO DE LA CARTERA (Lo que el director quiere ver siempre) ---
        
        // Unidades totales registradas en el sistema [cite: 15, 26]
        $totalUnits = LeaseContract::where('company_id', $companyId)->count();

        // Unidades Activas: Contratos con estatus 'active' [cite: 15, 27]
        $activeUnits = LeaseContract::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        // Unidades en Mora: Contratos que ya pasaron a 'past_due' [cite: 16, 114, 255]
        $pastDueUnits = LeaseContract::where('company_id', $companyId)
            ->where('status', 'past_due')
            ->count();

        // Unidades Inmovilizadas: Vehículos con el motor cortado actualmente [cite: 18, 35, 259]
        $immobilizedUnits = LeaseContract::where('company_id', $companyId)
            ->where('is_immobilized', true)
            ->count();

        // --- 2. ALERTAS DE RIESGO ---

        // Unidades Sin Señal: Dispositivos que no han reportado en las últimas 24 horas [cite: 17, 29, 118]
        // (Asumiendo que tienes la relación vehicle -> device con el campo last_update)
        $offlineUnits = LeaseContract::where('company_id', $companyId)
            ->whereHas('vehicle.device', function ($query) {
                $query->where('last_update', '<', Carbon::now()->subDay());
            })->count();

        // Unidades en Riesgo: Activas pero que ya pasaron su día de pago (están en gracia) [cite: 16, 43, 256]
        $atRiskUnits = LeaseContract::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereRaw('DAY(now()) > payment_day')
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_units'      => $totalUnits,
                'active_units'     => $activeUnits,
                'past_due_units'   => $pastDueUnits,
                'immobilized_units' => $immobilizedUnits,
                'offline_units'    => $offlineUnits,
                'at_risk_units'    => $atRiskUnits,
            ],
            'timestamp' => Carbon::now()->toDateTimeString()
        ]);
    }
}