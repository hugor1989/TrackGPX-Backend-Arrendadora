<?php

namespace App\Http\Controllers;

use App\Models\LeaseContract;
use App\Models\Vehicle;
use App\Models\LeasePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Jobs\ImmobilizeVehicleJob;
use Illuminate\Support\Facades\Log;

class LeaseContractController extends Controller
{
    // Listar contratos de la empresa (Multitenancy)
    public function index(Request $request)
    {
        // 1. Manejo dinámico de la paginación
        $perPage = $request->get('per_page', 15);

        // Si el front manda 'all', asignamos un número alto para traer todo en una sola "página"
        // Castamos a (int) si es número para evitar fallos en Eloquent
        $perPage = ($perPage === 'all') ? 9999 : (int) $perPage;

        $contracts = LeaseContract::where('company_id', $request->user()->company_id)
            ->with([
                'account:id,name,email,status',
                'account.customerProfile:account_id,phone_primary,rfc',
                'vehicle:id,name,plate,brand,model,year',
                'vehicle.device:id,vehicle_id,imei,status',
                // Sugerencia: Agregamos el último pago para saber cuándo fue la última vez que pagó
                'leasePayments' => fn($q) => $q->orderBy('payment_date', 'desc')->limit(1)
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('account', fn($a) => $a->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', "%{$search}%"));
                });
            })
            // Filtro de estatus (crucial para OverdueCreditsScreen)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $contracts,
        ]);
    }

    // Crear un nuevo contrato de arrendamiento
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'contract_number' => 'required|unique:lease_contracts',
            'monthly_amount' => 'required|numeric',
            'payment_day' => 'required|integer|between:1,31',
            'grace_days' => 'integer|min:0'
        ]);

        // Asegurar que el contrato pertenezca a la empresa del usuario logueado
        $validated['company_id'] = $request->user()->company_id;

        $contract = LeaseContract::create($validated);

        return response()->json([
            'message' => 'Contrato creado exitosamente',
            'contract' => $contract
        ], 201);
    }

    public function toggleLock(Request $request, $id)
    {
        $contract = LeaseContract::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $newStatus = !$contract->is_immobilized;

        $contract->update(['is_immobilized' => $newStatus]);

        $reason = $newStatus ? 'Bloqueo manual por administrador' : 'Desbloqueo manual';

        \App\Models\CommandLog::create([
            'vehicle_id'   => $contract->vehicle_id,
            'user_id'      => $request->user()->id,
            'command_type' => $newStatus ? 'engineStop' : 'engineResume',
            'action'       => $newStatus ? 'stop' : 'resume',
            'reason'       => $newStatus ? 'Bloqueo manual por administrador' : 'Desbloqueo manual',
            'status'       => true, // Marcamos como enviado
        ]);
        ImmobilizeVehicleJob::dispatch($contract->vehicle_id, $reason);

        return response()->json([
            'message' => $newStatus ? 'Vehículo bloqueado' : 'Vehículo desbloqueado',
            'is_immobilized' => $newStatus
        ]);
    }

    // REGISTRAR PAGO ACTUALIZADO
    public function registerPayment(Request $request, $id)
    {
        // LOG: Ver qué llega del Front
        Log::info('Iniciando registro de pago', [
            'contract_id' => $id,
            'user_id_auth' => Auth::id(),
            'user_id_request' => $request->user() ? $request->user()->id : 'Sin usuario',
            'datos_recibidos' => $request->all()
        ]);

        $request->validate([
            'amount' => 'required|numeric',
            'payment_date' => 'required|date',
            'reference' => 'nullable|string',
            'evidence' => 'nullable|image|max:2048',
        ]);

        $contract = LeaseContract::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $path = null;
        if ($request->hasFile('evidence')) {
            $path = $request->file('evidence')->store('payments/evidence', 'public');
        }

        // Aquí forzamos el uso del ID del usuario que viene en el token
        $userId = $request->user()->id;

        try {
            $payment = LeasePayment::create([
                'lease_contract_id' => $contract->id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'reference' => $request->reference,
                'month_paid' => date('Y-m', strtotime($request->payment_date)),
                'evidence_path' => $path,
                'created_by' => $userId, // Usamos la variable segura
            ]);

            $wasImmobilized = $contract->is_immobilized;
            $contract->update(['status' => 'active', 'is_immobilized' => false]);

            if ($wasImmobilized) {
                \App\Jobs\ImmobilizeVehicleJob::dispatch($contract->vehicle_id, 'Pago recibido', 'resume');
            }

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado correctamente'
            ]);
        } catch (\Exception $e) {
            // LOG: Capturar el error exacto
            Log::error('Error al guardar pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el historial de comandos de un vehículo específico del contrato.
     */
    public function getCommandHistory(Request $request, $contractId)
    {
        // 1. Buscamos el contrato asegurando que sea de la empresa del usuario
        $contract = LeaseContract::where('company_id', $request->user()->company_id)
            ->findOrFail($contractId);

        // 2. Traemos los logs del vehículo asociado, ordenados por los más recientes
        $logs = \App\Models\CommandLog::where('vehicle_id', $contract->vehicle_id)
            ->with('user:id,name') // Para saber qué administrador lo envió
            ->orderBy('created_at', 'desc')
            ->paginate(15); // Paginado para no saturar el front

        return response()->json([
            'status' => 'success',
            'data'   => $logs
        ]);
    }

    public function showCustomerProfile(Request $request, $id)
    {
        // Buscamos el contrato con todas sus relaciones cargadas (Eager Loading)
        $contract = LeaseContract::where('company_id', $request->user()->company_id)
            ->with([
                'account.customerProfile', // Datos personales, RFC, Emergencia
                'vehicle.device'           // Placas, VIN, Info de GPS
            ])
            ->findOrFail($id);

        // Cálculo de días de atraso (esencial para cobranza) [cite: 56, 115]
        $daysOverdue = 0;
        if ($contract->status === 'past_due') {
            $paymentDate = \Carbon\Carbon::now()->day($contract->payment_day);
            $daysOverdue = max(0, \Carbon\Carbon::now()->diffInDays($paymentDate, false));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'informacion_credito' => [
                    'cliente'         => $contract->account->name,
                    'rfc'             => $contract->account->customerProfile->rfc ?? 'N/D',
                    'telefono'        => $contract->account->customerProfile->phone_primary ?? 'N/D',
                    'numero_contrato' => $contract->contract_number,
                    'monto_financiado' => $contract->monthly_amount,
                    'dias_atraso'     => $daysOverdue, // Dato clave para cobranza [cite: 56]
                    'estatus'         => $contract->status,
                ],
                'informacion_vehiculo' => [
                    'vin'    => $contract->vehicle->vin, // [cite: 58]
                    'placa'  => $contract->vehicle->plate, // [cite: 59]
                    'modelo' => $contract->vehicle->model, // [cite: 60]
                    'año'    => $contract->vehicle->year, // [cite: 61]
                ],
                'informacion_gps' => [
                    'ultima_conexion' => $contract->vehicle->device->last_update ?? 'Sin señal', // [cite: 66]
                    'inmovilizado'    => (bool)$contract->is_immobilized, // [cite: 35]
                    'latitud'         => $contract->vehicle->device->last_lat ?? null,
                    'longitud'        => $contract->vehicle->device->last_lng ?? null,
                ],
                'contactos_recuperacion' => [ // Lo que hace a Track GPX diferente [cite: 134]
                    'emergencia_nombre' => $contract->account->customerProfile->emergency_contact_name ?? 'N/D',
                    'emergencia_tel'    => $contract->account->customerProfile->emergency_contact_phone ?? 'N/D',
                    'direccion_casa'    => $contract->account->customerProfile->address_home ?? 'N/D',
                    'direccion_oficina' => $contract->account->customerProfile->address_office ?? 'N/D',
                ]
            ]
        ]);
    }
}
