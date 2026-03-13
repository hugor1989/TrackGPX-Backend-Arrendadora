<?php

namespace App\Http\Controllers\Api\Fine;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use App\Models\Fine; // Asegúrate de tener este modelo
use App\Models\Vehicle;
use App\Models\VehicleExpense; // Modelo para registrar el gasto
use Illuminate\Support\Facades\DB;
use App\Exports\FinesExport;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class FineController extends AppBaseController
{
    // 1. LISTAR MULTAS (Con filtros)
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Fine::with([
            'vehicle:id,name,plate',
            'vehicle.leaseContracts' => fn($q) => $q
                ->whereIn('status', ['active', 'past_due', 'legal_process'])
                ->select('id', 'vehicle_id', 'account_id', 'contract_number', 'monthly_amount', 'status'),
            'vehicle.leaseContracts.account:id,name,email',
            'vehicle.leaseContracts.account.customerProfile:account_id,phone_primary,rfc',
        ])
            ->where('company_id', $user->company_id);

        // Filtro por status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Búsqueda por placa o referencia
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas(
                        'vehicle',
                        fn($v) =>
                        $v->where('plate', 'like', "%{$search}%")
                            ->orWhere('name',  'like', "%{$search}%")
                    );
            });
        }

        $fines = $query->orderBy('detected_at', 'desc')->get();

        $pendingAmount = Fine::where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'data'    => $fines,
            'summary' => [
                'pending_count'  => $fines->where('status', 'pending')->count(),
                'paid_count'     => $fines->where('status', 'paid')->count(),
                'pending_amount' => (float) $pendingAmount,
            ]
        ]);
    }
    // 2. MARCAR COMO PAGADA
    public function markAsPaid(Request $request, $id)
    {
        // Usamos una transacción: O se guardan los dos, o no se guarda ninguno (seguridad)
        return DB::transaction(function () use ($id, $request) {

            // 1. Buscamos la multa
            $fine = Fine::findOrFail($id);

            // Validación simple: Si ya está pagada, no hacemos nada
            if ($fine->status === 'paid') {
                return response()->json(['message' => 'Esta multa ya estaba pagada'], 400);
            }

            // 2. Actualizamos la multa a PAGADA
            $fine->update([
                'status' => 'paid',
                'paid_at' => now(), // Sería bueno tener este campo en tu tabla fines
            ]);

            // 3. ¡AQUÍ ESTÁ EL TRUCO! Creamos el Gasto automáticamente
            VehicleExpense::create([
                'vehicle_id' => $fine->vehicle_id,
                'date' => now(), // Fecha del pago
                'type' => 'Multa', // O 'Legal', como lo manejes
                'amount' => $fine->amount,
                'description' => "Pago de Multa Folio: {$fine->reference}. Motivo: {$fine->description}",
                // Opcional: Si en el futuro subes comprobante, iría aquí
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multa pagada y gasto registrado correctamente'
            ]);
        });
    }

    public function history(Request $request)
    {
        $query = Fine::with([
            // 1. Cargamos el vehículo
            'vehicle',

            // 2. Usamos tu relación 'driver' y le pedimos la 'account' (donde está el nombre)
            'vehicle.driver.account',

            // 3. Cargamos el grupo y su supervisor
            'vehicle.group.supervisor'
        ])
            ->where('company_id', auth()->user()->company_id);

        // 1. Filtro por FECHAS
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // 2. Filtro por GRUPO (Flota)
        if ($request->has('group_id') && $request->group_id != 'all') {
            $query->whereHas('vehicle', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            });
        }

        // 3. Filtro por ESTATUS
        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        // 4. Búsqueda por PLACA
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->whereHas('vehicle', function ($q) use ($search) {
                $q->where('plate', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $fines = $query->orderBy('created_at', 'desc')->get();

        // Calculamos Resumen al vuelo
        $totalAmount = $fines->sum('amount');
        $paidCount = $fines->where('status', 'paid')->count();
        $pendingCount = $fines->where('status', 'pending')->count();

        return response()->json([
            'data' => $fines,
            'stats' => [
                'total_amount' => $totalAmount,
                'paid_count' => $paidCount,
                'pending_count' => $pendingCount,
                'total_count' => $fines->count()
            ]
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->all();
        $type = $request->input('format', 'xlsx'); // xlsx, csv, pdf

        // Opción A: EXCEL o CSV
        if ($type === 'xlsx' || $type === 'csv') {
            return Excel::download(new FinesExport($filters), 'multas_reporte.' . $type);
        }

        // Opción B: PDF
        if ($type === 'pdf') {
            // Reutilizamos la query del Export para no repetir código lógica
            $exporter = new FinesExport($filters);
            $fines = $exporter->query()->get(); // Ejecutamos la consulta

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.fines_pdf', compact('fines'))
                ->setPaper('a4', 'landscape'); // Horizontal para que quepan columnas

            return $pdf->download('multas_reporte.pdf');
        }
    }
}
