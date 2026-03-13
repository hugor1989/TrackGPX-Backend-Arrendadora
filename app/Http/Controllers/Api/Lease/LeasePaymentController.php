<?php

namespace App\Http\Controllers\Api\Lease;

use App\Http\Controllers\Controller;
use App\Models\LeasePayment;
use App\Models\LeaseContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeasePaymentController extends Controller
{
    /**
     * Listado general de pagos (Para PaymentsScreen)
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $payments = LeasePayment::whereHas('leaseContract', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->with([
                'leaseContract.account:id,name',
                'leaseContract.vehicle:id,plate,name'
            ])
            // 1. Búsqueda por cliente, placa o referencia
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('leaseContract.account', fn($a) =>
                        $a->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('leaseContract.vehicle', fn($v) =>
                        $v->where('plate', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            // 2. Filtro por rango de fechas
            ->when($request->date_from, fn($q, $d) => $q->whereDate('payment_date', '>=', $d))
            ->when($request->date_to,   fn($q, $d) => $q->whereDate('payment_date', '<=', $d))
            // 3. Filtro por contrato específico
            ->when($request->contract_id, fn($q, $id) => $q->where('lease_contract_id', $id))
            // 4. Filtro por mes (ej: 2026-03)
            ->when($request->month, fn($q, $m) => $q->where('month_paid', $m))
            ->orderBy('payment_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $payments
        ]);
    }

    /**
     * Detalle de un pago específico
     */
    public function show($id)
    {
        $payment = LeasePayment::with(['leaseContract.account', 'leaseContract.vehicle'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }

    /**
     * Eliminar un pago (Opcional - solo si tienes permisos de superadmin)
     */
    public function destroy($id)
    {
        $payment = LeasePayment::findOrFail($id);

        // Si hay evidencia, la borramos del storage
        if ($payment->evidence_path) {
            Storage::disk('public')->delete($payment->evidence_path);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro de pago eliminado'
        ]);
    }
}
