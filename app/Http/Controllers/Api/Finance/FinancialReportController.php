<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VehicleExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Fine;

class FinancialReportController extends Controller
{
    /* public function getExpenses(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()));
            $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()));
            $companyId = $request->user()->company_id;

            // 1. Consulta Base
            $query = VehicleExpense::whereBetween('date', [$startDate, $endDate])
                ->whereHas('vehicle', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->with('vehicle');

            if ($request->has('vehicle_id') && $request->vehicle_id) {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            $expenses = $query->orderBy('date', 'desc')->get();

            // 2. Calcular Totales por Categoría (Para las tarjetas del dashboard)
            $breakdown = $expenses->groupBy('type')->map(function ($row) {
                return $row->sum('amount');
            });

            $total = $expenses->sum('amount');

            // 3. Formatear la data para la tabla
            $formattedData = $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'vehicle' => $expense->vehicle->brand . ' ' . $expense->vehicle->plate,
                    'date' => $expense->date->format('Y-m-d'),
                    'type' => $this->translateType($expense->type),
                    'type_raw' => $expense->type,
                    'amount' => $expense->amount,
                    'description' => $expense->description ?? '-'
                ];
            });

            return response()->json([
                'success' => true,
                'summary' => [
                    'total' => $total,
                    'fuel' => $breakdown['FUEL'] ?? 0,
                    'maintenance' => ($breakdown['MAINTENANCE'] ?? 0) + ($breakdown['REPAIR'] ?? 0),
                    'others' => $total - ($breakdown['FUEL'] ?? 0) - (($breakdown['MAINTENANCE'] ?? 0) + ($breakdown['REPAIR'] ?? 0))
                ],
                'data' => $formattedData
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    } */

    public function getExpenses(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()));
            $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()));
            $companyId = $request->user()->company_id;
            $vehicleId = $request->input('vehicle_id');

            // ======================================================
            // 1. OBTENER GASTOS (Tabla vehicle_expenses)
            // ======================================================
            $expensesQuery = VehicleExpense::whereBetween('date', [$startDate, $endDate])
                ->whereHas('vehicle', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->with('vehicle');

            if ($vehicleId) {
                $expensesQuery->where('vehicle_id', $vehicleId);
            }
            $expenses = $expensesQuery->get();

            // ======================================================
            // 2. OBTENER MULTAS (Tabla fines) - ¡NUEVO! 🚨
            // ======================================================
            $finesQuery = Fine::whereBetween('created_at', [$startDate, $endDate]) // Nota: usamos detected_at
                ->where('company_id', $companyId)
                ->with('vehicle');

            if ($vehicleId) {
                $finesQuery->where('vehicle_id', $vehicleId);
            }
            $fines = $finesQuery->get();


            // ======================================================
            // 3. UNIFICAR Y FORMATEAR DATOS
            // ======================================================
            
            // A. Formateamos Gastos
            $formattedExpenses = $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'source_table' => 'expenses', // Para saber de donde viene
                    'vehicle' => $expense->vehicle->brand . ' ' . $expense->vehicle->plate,
                    'date' => $expense->date->format('Y-m-d'),
                    'type' => $this->translateType($expense->type),
                    'type_raw' => $expense->type,
                    'attachment' => $expense->attachment,
                    'amount' => (float) $expense->amount,
                    'description' => $expense->description ?? '-',
                    'status' => 'paid' // Los gastos registrados suelen ser pagados
                ];
            });

            // B. Formateamos Multas (Para que tengan la misma estructura)
            $formattedFines = $fines->map(function ($fine) {
                return [
                    'id' => $fine->id,
                    'source_table' => 'fines',
                    'vehicle' => $fine->vehicle->brand . ' ' . $fine->vehicle->plate,
                    'date' => Carbon::parse($fine->created_at)->format('Y-m-d'), // Convertimos created_at a date
                    'type' => 'Multa',
                    'type_raw' => 'FINE', // Clave para tu Frontend
                    'attachment' => null,
                    'amount' => (float) $fine->amount,
                    'description' => $fine->description ?? ($fine->source . ' - ' . $fine->reference),
                    'status' => $fine->status // 'pending', 'paid', etc.
                ];
            });

            // C. FUSIONAR Y ORDENAR (El Merge Maestro)
            // Unimos las dos colecciones y ordenamos por fecha descendente
            $allRecords = $formattedExpenses->merge($formattedFines)->sortByDesc('date')->values();


            // ======================================================
            // 4. CALCULAR TOTALES (Summary)
            // ======================================================
            $totalExpenses = $expenses->sum('amount');
            $totalFines = $fines->sum('amount');
            $grandTotal = $totalExpenses + $totalFines;

            // Desglose de gastos operativos (sin multas)
            $expenseBreakdown = $expenses->groupBy('type')->map(function ($row) {
                return $row->sum('amount');
            });

            return response()->json([
                'success' => true,
                'summary' => [
                    'total' => $grandTotal,
                    'fuel' => $expenseBreakdown['FUEL'] ?? 0,
                    'maintenance' => ($expenseBreakdown['MAINTENANCE'] ?? 0) + ($expenseBreakdown['REPAIR'] ?? 0),
                    'fines' => $totalFines, // Total explícito de multas
                    'others' => $totalExpenses - ($expenseBreakdown['FUEL'] ?? 0) - (($expenseBreakdown['MAINTENANCE'] ?? 0) + ($expenseBreakdown['REPAIR'] ?? 0))
                ],
                'data' => $allRecords // <--- Aquí va la lista mezclada
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function translateType($type)
    {
        $map = [
            'FUEL' => 'Combustible',
            'MAINTENANCE' => 'Mantenimiento',
            'REPAIR' => 'Reparación',
            'INSURANCE' => 'Seguro',
            'FINE' => 'Multa',
            'TOLL' => 'Peaje',
            'OTHER' => 'Otro'
        ];
        return $map[$type] ?? $type;
    }

    public function store(Request $request)
    {
        // 🔍 DEBUG: Ver qué diablos está llegando
        Log::info('--- INTENTO DE SUBIDA DE GASTO ---');
        Log::info('Headers recibidos:', $request->headers->all());
        Log::info('Es Multipart?: ' . ($request->isJson() ? 'NO (Es JSON)' : 'SI (Es Multipart)'));
        Log::info('Data Texto:', $request->except('attachment')); // Todo menos el archivo
        Log::info('Tiene Archivo?: ' . ($request->hasFile('attachment') ? 'SI' : 'NO'));

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            Log::info('Detalles Archivo:', [
                'Nombre' => $file->getClientOriginalName(),
                'MimeType' => $file->getMimeType(),
                'Tamaño' => $file->getSize() . ' bytes'
            ]);
        }
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date' => 'required|date',
            'type' => 'required|string',
            'amount' => 'required|numeric',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        try {
            $data = $request->all();

            // Subida de imagen
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('tickets', 'public');
                $data['attachment'] = asset('storage/' . $path);
            }

            $expense = VehicleExpense::create($data);

            return response()->json(['success' => true, 'data' => $expense]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
