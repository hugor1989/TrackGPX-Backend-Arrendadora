<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Account, Vehicle, LeaseContract, AlertLog};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionReportController extends Controller
{
    /**
     * 1. GESTIÓN DE COBRANZA
     * Lista de clientes con contrato 'past_due' (vencidos recientes)
     */
    public function getCollectionManagement(Request $request)
    {
        $companyId = $request->user()->company_id;

        $collections = Account::where('company_id', $companyId)
            ->where('role', 'customer')
            ->whereHas('leaseContract', function ($query) {
                $query->where('status', 'past_due');
            })
            ->with(['leaseContract', 'customerProfile', 'riskScore', 'latestPosition'])
            ->get()
            ->map(function ($account) {
                $contract = $account->leaseContract;
                
                // Cálculo de fecha de vencimiento: 
                // Si hoy es día 20 y su día de pago es el 15, debe el mes actual.
                $dueDate = Carbon::now()->day($contract->payment_day);
                if ($dueDate->isFuture()) {
                    $dueDate->subMonth();
                }

                return [
                    'account_id'      => $account->id,
                    'name'            => $account->name,
                    'contract_number' => $contract->contract_number,
                    'phone'           => $account->customerProfile->phone_primary ?? 'Sin teléfono',
                    'days_overdue'    => (int) Carbon::now()->diffInDays($dueDate),
                    'monthly_amount'  => (float) $contract->monthly_amount,
                    'payment_day'     => $contract->payment_day,
                    'risk_level'      => $account->riskScore->score ?? 'Medio',
                    'risk_points'     => $account->riskScore->points ?? 0,
                    'is_immobilized'  => (bool) $contract->is_immobilized,
                    'last_lat'        => $account->latestPosition->latitude ?? null,
                    'last_lng'        => $account->latestPosition->longitude ?? null,
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $collections,
            'summary' => [
                'total_pending' => $collections->sum('monthly_amount'),
                'count'         => $collections->count()
            ]
        ]);
    }

    /**
     * 2. UNIDADES A RECUPERAR
     * Unidades con mora crítica o proceso legal + Riesgo Alto
     */
    public function getUnitsToRecover(Request $request)
    {
        $companyId = $request->user()->company_id;

        $units = Vehicle::where('company_id', $companyId)
            ->whereHas('currentLease', function ($query) {
                $query->whereIn('status', ['past_due', 'legal_process']);
            })
            ->with(['currentLease', 'currentCustomer', 'lastPosition', 'device.simCard'])
            ->get()
            ->map(function ($vehicle) {
                $contract = $vehicle->currentLease;
                
                return [
                    'id'              => $vehicle->id,
                    'plate'           => $vehicle->plate,
                    'vehicle_name'    => $vehicle->name,
                    'customer_name'   => $vehicle->currentCustomer->name ?? 'N/A',
                    'contract_status' => $contract->status,
                    'is_immobilized'  => (bool) $contract->is_immobilized,
                    'imei'            => $vehicle->device->imei ?? 'N/A',
                    'phone_number'    => $vehicle->device->simCard->phone_number ?? 'N/A',
                    'speed'           => $vehicle->lastPosition->speed ?? 0,
                    'latitude'        => $vehicle->lastPosition->latitude ?? null,
                    'longitude'       => $vehicle->lastPosition->longitude ?? null,
                    'last_update'     => $vehicle->lastPosition->timestamp ?? null,
                    'priority'        => $contract->status === 'legal_process' ? 'CRÍTICA' : 'ALTA'
                ];
            })
            ->sortByDesc('priority')
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $units
        ]);
    }

    /**
     * 3. HISTORIAL DE RECUPERACIONES
     * Basado en alertas críticas que sugieren que la unidad fue recuperada o movida
     */
    public function getRecoveryHistory(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Buscamos alertas de arrastre, corte de energía o inmovilizaciones exitosas
        $history = AlertLog::where('company_id', $companyId)
            ->whereIn('type', ['towing', 'power_cut', 'ignition_off'])
            ->with(['vehicle', 'vehicle.currentCustomer'])
            ->orderBy('occurred_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id'            => $log->id,
                    'event'         => $log->message,
                    'type'          => $log->type,
                    'date'          => $log->occurred_at->format('d M Y, H:i'),
                    'vehicle'       => $log->vehicle->plate ?? 'N/A',
                    'customer'      => $log->vehicle->currentCustomer->name ?? 'N/A',
                    'location_link' => "https://www.google.com/maps?q={$log->latitude},{$log->longitude}"
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $history
        ]);
    }
}