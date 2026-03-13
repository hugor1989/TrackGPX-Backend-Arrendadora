<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Vehicle;
use App\Models\LeasePayment;
use App\Models\LeaseContract;

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

     // ── 1. SALUD DE CARTERA ───────────────────────────────────────────────────
    public function portfolioHealth()
    {
        $user      = auth()->user();
        $companyId = $user->company_id;
        $company   = $user->company;
 
        // Totales por status
        $contracts = LeaseContract::where('company_id', $companyId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active'        THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'past_due'      THEN 1 ELSE 0 END) as past_due,
                SUM(CASE WHEN status = 'legal_process' THEN 1 ELSE 0 END) as legal_process,
                SUM(CASE WHEN status = 'finished'      THEN 1 ELSE 0 END) as finished,
                SUM(monthly_amount) as total_monthly
            ")
            ->first();
 
        // Cobranza últimos 6 meses
        $monthlyCollection = [];
        for ($i = 5; $i >= 0; $i--) {
            $date     = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
 
            $expected = LeaseContract::where('company_id', $companyId)
                ->whereIn('status', ['active', 'past_due', 'legal_process'])
                ->where('created_at', '<=', $date->endOfMonth())
                ->sum('monthly_amount');
 
            $collected = LeasePayment::whereHas('leaseContract', fn($q) =>
                    $q->where('company_id', $companyId)
                )
                ->whereYear('payment_date',  $date->year)
                ->whereMonth('payment_date', $date->month)
                ->sum('amount');
 
            $monthlyCollection[] = [
                'month'     => $date->locale('es')->isoFormat('MMM YY'),
                'expected'  => (float) $expected,
                'collected' => (float) $collected,
            ];
        }
 
        // Total recaudado vs esperado (mes actual)
        $thisMonth = Carbon::now();
        $totalExpected = LeaseContract::where('company_id', $companyId)
            ->whereIn('status', ['active', 'past_due', 'legal_process'])
            ->sum('monthly_amount');
 
        $totalCollected = LeasePayment::whereHas('leaseContract', fn($q) =>
                $q->where('company_id', $companyId)
            )
            ->whereYear('payment_date',  $thisMonth->year)
            ->whereMonth('payment_date', $thisMonth->month)
            ->sum('amount');
 
        $collectionRate = $totalExpected > 0
            ? round(($totalCollected / $totalExpected) * 100, 1)
            : 0;
 
        // Top 10 contratos más atrasados
        $topOverdue = LeaseContract::where('company_id', $companyId)
            ->whereIn('status', ['past_due', 'legal_process'])
            ->with([
                'account:id,name',
                'vehicle:id,name,plate',
            ])
            ->get()
            ->map(function ($c) {
                $lastPayment = LeasePayment::where('lease_contract_id', $c->id)
                    ->orderByDesc('payment_date')
                    ->first();
 
                $daysOverdue = $lastPayment
                    ? Carbon::parse($lastPayment->payment_date)->diffInDays(now())
                    : Carbon::parse($c->created_at)->diffInDays(now());
 
                $monthsOverdue = (int) ceil($daysOverdue / 30);
                $balance       = $monthsOverdue * $c->monthly_amount;
 
                return [
                    'contract_number' => $c->contract_number,
                    'client'          => $c->account?->name ?? '—',
                    'vehicle'         => ($c->vehicle?->name ?? '—') . ' ' . ($c->vehicle?->plate ?? ''),
                    'days_overdue'    => $daysOverdue,
                    'balance'         => $balance,
                    'monthly_amount'  => (float) $c->monthly_amount,
                    'status'          => $c->status,
                ];
            })
            ->sortByDesc('days_overdue')
            ->take(10)
            ->values();
 
        return response()->json([
            'summary' => [
                'total'            => (int)   $contracts->total,
                'active'           => (int)   $contracts->active,
                'past_due'         => (int)   $contracts->past_due,
                'legal_process'    => (int)   $contracts->legal_process,
                'finished'         => (int)   $contracts->finished,
                'collection_rate'  => $collectionRate,
                'total_expected'   => (float) $totalExpected,
                'total_collected'  => (float) $totalCollected,
            ],
            'monthly_collection' => $monthlyCollection,
            'top_overdue'        => $topOverdue,
            'company' => [
                'name'     => $company->name     ?? 'Mi Arrendadora',
                'logo_url' => $company->logo_url ?? null,
                'rfc'      => $company->rfc      ?? null,
            ],
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
 
    // ── 2. UNIDADES EN RIESGO ────────────────────────────────────────────────
    public function unitsAtRisk()
    {
        $user      = auth()->user();
        $companyId = $user->company_id;
        $company   = $user->company;
 
        $contracts = LeaseContract::where('company_id', $companyId)
            ->whereIn('status', ['past_due', 'legal_process'])
            ->with([
                'account:id,name,email',
                'account.customerProfile:account_id,phone_primary,rfc',
                'vehicle:id,name,plate,brand,model,year',
                'vehicle.device:id,vehicle_id,status,activated_at',
                'vehicle.lastPosition',
            ])
            ->get()
            ->map(function ($c) {
                $lastPayment = LeasePayment::where('lease_contract_id', $c->id)
                    ->orderByDesc('payment_date')->first();
 
                $daysOverdue   = $lastPayment
                    ? Carbon::parse($lastPayment->payment_date)->diffInDays(now())
                    : Carbon::parse($c->created_at)->diffInDays(now());
                $monthsOverdue = (int) ceil($daysOverdue / 30);
 
                $pos = $c->vehicle?->lastPosition;
 
                return [
                    'contract_number'  => $c->contract_number,
                    'status'           => $c->status,
                    'client'           => $c->account?->name          ?? '—',
                    'phone'            => $c->account?->customerProfile?->phone_primary ?? '—',
                    'rfc'              => $c->account?->customerProfile?->rfc           ?? '—',
                    'vehicle'          => ($c->vehicle?->brand ?? '') . ' ' . ($c->vehicle?->model ?? '') . ' ' . ($c->vehicle?->year ?? ''),
                    'plate'            => $c->vehicle?->plate          ?? '—',
                    'monthly_amount'   => (float) $c->monthly_amount,
                    'days_overdue'     => $daysOverdue,
                    'months_overdue'   => $monthsOverdue,
                    'balance'          => $monthsOverdue * $c->monthly_amount,
                    'is_immobilized'   => (bool) $c->is_immobilized,
                    'gps_online'       => (bool) $c->vehicle?->device?->is_activated,
                    'last_gps'         => $pos?->timestamp
                        ? Carbon::parse($pos->timestamp)->diffForHumans()
                        : 'Sin señal',
                    'latitude'         => $pos?->latitude,
                    'longitude'        => $pos?->longitude,
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();
 
        return response()->json([
            'data'    => $contracts,
            'summary' => [
                'total'         => $contracts->count(),
                'past_due'      => $contracts->where('status', 'past_due')->count(),
                'legal_process' => $contracts->where('status', 'legal_process')->count(),
                'total_balance' => $contracts->sum('balance'),
            ],
            'company'      => [
                'name'     => $company->name     ?? 'Mi Arrendadora',
                'logo_url' => $company->logo_url ?? null,
            ],
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
 
    // ── 3. UNIDADES SIN SEÑAL ────────────────────────────────────────────────
    public function offlineUnits()
    {
        $user      = auth()->user();
        $companyId = $user->company_id;
        $company   = $user->company;
        $threshold = Carbon::now()->subMinutes(60); // sin señal > 60 min
 
        $vehicles = Vehicle::where('company_id', $companyId)
            ->with([
                'device',
                'lastPosition',
                'currentAssignment.account:id,name',
                'currentAssignment.account.customerProfile:account_id,phone_primary',
                'leaseContracts' => fn($q) => $q
                    ->whereIn('status', ['active', 'past_due', 'legal_process'])
                    ->select('id', 'vehicle_id', 'status', 'contract_number')
                    ->limit(1),
            ])
            ->get()
            ->filter(function ($v) use ($threshold) {
                $lastTs = $v->lastPosition?->timestamp;
                if (!$lastTs) return true; // nunca ha reportado
                return Carbon::parse($lastTs)->lt($threshold);
            })
            ->map(function ($v) {
                $pos        = $v->lastPosition;
                $contract   = $v->leaseContracts->first();
                $lastReport = $pos?->timestamp
                    ? Carbon::parse($pos->timestamp)->diffForHumans()
                    : 'Nunca';
                $hoursOff   = $pos?->timestamp
                    ? Carbon::parse($pos->timestamp)->diffInHours(now())
                    : null;
 
                return [
                    'vehicle'          => $v->name,
                    'plate'            => $v->plate,
                    'client'           => $v->currentAssignment?->account?->name ?? 'Sin arrendatario',
                    'phone'            => $v->currentAssignment?->account?->customerProfile?->phone_primary ?? '—',
                    'contract_number'  => $contract?->contract_number ?? '—',
                    'contract_status'  => $contract?->status          ?? 'no_contract',
                    'device_status'    => $v->device?->status         ?? '—',
                    'last_report'      => $lastReport,
                    'hours_offline'    => $hoursOff,
                    'last_latitude'    => $pos?->latitude,
                    'last_longitude'   => $pos?->longitude,
                ];
            })
            ->sortByDesc('hours_offline')
            ->values();
 
        return response()->json([
            'data'    => $vehicles,
            'summary' => [
                'total_offline'        => $vehicles->count(),
                'never_reported'       => $vehicles->whereNull('hours_offline')->count(),
                'with_active_contract' => $vehicles->whereNotIn('contract_status', ['no_contract', 'finished'])->count(),
            ],
            'company'      => [
                'name'     => $company->name     ?? 'Mi Arrendadora',
                'logo_url' => $company->logo_url ?? null,
            ],
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
}