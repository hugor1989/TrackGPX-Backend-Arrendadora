<?php

namespace App\Http\Controllers\Api\Vehicles;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleExpense;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VehicleController extends Controller
{
    /**
     * Obtener todos los vehículos de la empresa
     * GET /api/vehicles
     */
    public function index()
    {
        try {
            $vehicles = auth()->user()->company->vehicles()
                ->with([
                    'driver.account',
                    'device',
                    'currentAssignment.driver.account',
                    'group',
                    'lastPosition' // ← agregar esto
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($vehicle) {
                    $pos = $vehicle->lastPosition;

                    // Inyectar campos de posición directamente en el vehículo
                    $vehicle->latitude  = $pos->latitude ?? null;
                    $vehicle->longitude = $pos->longitude ?? null;
                    $vehicle->speed     = $pos->speed ?? 0;
                    $vehicle->heading   = $pos->heading ?? 0;
                    $vehicle->last_gps  = $pos?->timestamp
                        ? \Carbon\Carbon::parse($pos->timestamp)->diffForHumans()
                        : 'Sin señal';

                    return $vehicle;
                });

            return response()->json([
                'success' => true,
                'data' => $vehicles
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vehículos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un vehículo específico
     * GET /api/vehicles/{id}
     */
    public function show($id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()
                ->with([
                    'driver.account',
                    'device',
                    'currentAssignment.driver.account'
                ])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $vehicle
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehículo no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear un nuevo vehículo
     * POST /api/vehicles
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'plate' => 'required|string|max:50',
                'vin' => 'nullable|string|max:17',
                'type' => 'nullable|string|max:50',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'odometer' => 'nullable|integer|min:0',
                'status' => 'nullable|in:active,inactive,maintenance',
                'driver_id' => 'nullable|exists:drivers,id',
            ]);

            // Agregar company_id
            $validated['company_id'] = auth()->user()->company_id;
            $validated['status'] = $validated['status'] ?? 'active';

            DB::beginTransaction();

            // Crear vehículo
            $vehicle = Vehicle::create($validated);

            // Si se asignó un conductor, crear el registro de asignación
            if (!empty($validated['driver_id'])) {
                VehicleAssignment::create([
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $validated['driver_id'],
                    'assigned_from' => now(),
                    'active' => true,
                ]);
            }

            DB::commit();

            // Recargar con relaciones
            $vehicle->load(['driver.account', 'device', 'currentAssignment']);

            return response()->json([
                'success' => true,
                'message' => 'Vehículo creado correctamente',
                'data' => $vehicle
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear vehículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un vehículo
     * PUT /api/vehicles/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'plate' => 'sometimes|required|string|max:50',
                'vin' => 'nullable|string|max:17',
                'type' => 'nullable|string|max:50',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'odometer' => 'nullable|integer|min:0',
                'status' => 'nullable|in:active,inactive,maintenance',
                'driver_id' => 'nullable|exists:drivers,id',
                'device_id' => 'nullable|exists:devices,id',
                'map_icon' => 'nullable|string|max:100',
            ]);

            DB::beginTransaction();

            // ✅ LÓGICA CORREGIDA PARA EL CONDUCTOR
            // Usamos array_key_exists para detectar si se envió "driver_id" (aunque sea null)
            if (array_key_exists('driver_id', $validated)) {

                // 1. Siempre cerramos la asignación anterior activa de este vehículo
                VehicleAssignment::where('vehicle_id', $vehicle->id)
                    ->where('active', true)
                    ->update([
                        'assigned_to' => now(),
                        'active' => false,
                    ]);

                // 2. Si el nuevo driver_id NO es null/vacio, creamos la nueva asignación
                if (!empty($validated['driver_id'])) {
                    VehicleAssignment::create([
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $validated['driver_id'],
                        'assigned_from' => now(),
                        'active' => true,
                    ]);
                }
            }

            // ✅ LÓGICA SIMILAR PARA DISPOSITIVO (si lo manejas en update)
            if (array_key_exists('device_id', $validated)) {
                // Si la lógica de dispositivos es directa en la tabla vehicles, el $vehicle->update lo maneja.
                // Si tienes una tabla pivote para dispositivos, agrega la lógica aquí.
            }

            // Actualizar tabla vehicles
            $vehicle->update($validated);

            DB::commit();

            // Recargar con relaciones
            $vehicle->load(['driver.account', 'device']);

            return response()->json([
                'success' => true,
                'message' => 'Vehículo actualizado correctamente',
                'data' => $vehicle
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar vehículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un vehículo
     * DELETE /api/vehicles/{id}
     */
    public function destroy($id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            DB::beginTransaction();

            // Finalizar asignaciones activas
            VehicleAssignment::where('vehicle_id', $vehicle->id)
                ->where('active', true)
                ->update([
                    'assigned_to' => now(),
                    'active' => false,
                ]);

            // Eliminar vehículo
            $vehicle->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehículo eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar vehículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar conductor a vehículo
     * POST /api/vehicles/{id}/assign-driver
     */
    public function assignDriver(Request $request, $id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            $validated = $request->validate([
                'driver_id' => 'required|exists:drivers,id',
            ]);

            DB::beginTransaction();

            // Verificar que el conductor pertenece a la misma empresa
            $driver = auth()->user()->company->drivers()->findOrFail($validated['driver_id']);

            // Finalizar asignación actual del vehículo
            VehicleAssignment::where('vehicle_id', $vehicle->id)
                ->where('active', true)
                ->update([
                    'assigned_to' => now(),
                    'active' => false,
                ]);

            // Finalizar asignación actual del conductor (si tiene otro vehículo)
            VehicleAssignment::where('driver_id', $driver->id)
                ->where('active', true)
                ->update([
                    'assigned_to' => now(),
                    'active' => false,
                ]);

            // Crear nueva asignación
            $assignment = VehicleAssignment::create([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'assigned_from' => now(),
                'active' => true,
            ]);

            // Actualizar driver_id en vehicle
            $vehicle->update(['driver_id' => $driver->id]);

            DB::commit();

            // Recargar con relaciones
            $assignment->load('driver.account');

            return response()->json([
                'success' => true,
                'message' => 'Conductor asignado correctamente',
                'data' => $assignment
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar conductor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desasignar conductor de vehículo
     * DELETE /api/vehicles/{id}/assign-driver
     */
    public function unassignDriver($id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            DB::beginTransaction();

            // Finalizar asignación activa
            VehicleAssignment::where('vehicle_id', $vehicle->id)
                ->where('active', true)
                ->update([
                    'assigned_to' => now(),
                    'active' => false,
                ]);

            // Actualizar driver_id en vehicle
            $vehicle->update(['driver_id' => null]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conductor desasignado correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al desasignar conductor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar dispositivo GPS a vehículo
     * POST /api/vehicles/{id}/assign-device
     */
    public function assignDevice(Request $request, $id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            $validated = $request->validate([
                'device_id' => 'required|exists:devices,id',
            ]);

            DB::beginTransaction();

            // Verificar que el dispositivo pertenece a la misma empresa
            $device = auth()->user()->company->devices()->findOrFail($validated['device_id']);

            // Desasignar dispositivo de otro vehículo si está asignado
            Vehicle::where('id', '!=', $vehicle->id)
                ->where('device_id', $device->id)
                ->update(['device_id' => null]);

            // Asignar dispositivo al vehículo
            $vehicle->update(['device_id' => $device->id]);

            // Actualizar vehicle_id en device
            $device->update(['vehicle_id' => $vehicle->id]);

            DB::commit();

            // Recargar con relaciones
            $vehicle->load(['driver.account', 'device']);

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo GPS asignado correctamente',
                'data' => $vehicle
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar dispositivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desasignar dispositivo GPS de vehículo
     * DELETE /api/vehicles/{id}/assign-device
     */
    public function unassignDevice($id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            DB::beginTransaction();

            if ($vehicle->device_id) {
                // Actualizar vehicle_id en device
                $device = $vehicle->device;
                if ($device) {
                    $device->update(['vehicle_id' => null]);
                }
            }

            // Actualizar device_id en vehicle
            $vehicle->update(['device_id' => null]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo GPS desasignado correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al desasignar dispositivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // --- 1. ENDPOINT EXCLUSIVO PARA SEGUROS ---
    public function updateInsurance(Request $request, $id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            $validated = $request->validate([
                'insurance_company' => 'required|string|max:100',
                'policy_number' => 'required|string|max:100',
                'policy_expiry' => 'required|date',
                'policy_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'amount' => 'nullable|numeric|min:0' // Costo para el gasto
            ]);

            DB::beginTransaction();

            // A. Subir Archivo
            if ($request->hasFile('policy_document')) {
                $path = $request->file('policy_document')->store('policies', 'public');
                $validated['policy_document_url'] = asset('storage/' . $path);
            }

            // B. Actualizar Vehículo (Semáforo)
            $vehicle->update([
                'insurance_company' => $validated['insurance_company'],
                'policy_number' => $validated['policy_number'],
                'policy_expiry' => $validated['policy_expiry'],
                'policy_document_url' => $validated['policy_document_url'] ?? $vehicle->policy_document_url
            ]);

            // C. Registrar Gasto (Historial)
            if ($request->filled('amount') && $request->amount > 0) {
                VehicleExpense::create([
                    'vehicle_id' => $vehicle->id,
                    'type' => 'INSURANCE',
                    'date' => now(),
                    'amount' => $request->amount,
                    'description' => "Renovación Póliza {$validated['policy_number']}",
                    'attachment' => $validated['policy_document_url'] ?? null
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Póliza actualizada']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // --- 2. ENDPOINT EXCLUSIVO PARA MANTENIMIENTO ---
    public function registerMaintenance(Request $request, $id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            $validated = $request->validate([
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'date' => 'required|date',
                'current_odometer' => 'nullable|numeric',
                'attachment' => 'nullable|image|max:10240',
            ]);

            DB::beginTransaction();

            // A. Subir Ticket
            $attachmentUrl = null;
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('expenses', 'public');
                $attachmentUrl = asset('storage/' . $path);
            }

            // B. Registrar Gasto (Historial)
            VehicleExpense::create([
                'vehicle_id' => $vehicle->id,
                'type' => 'MAINTENANCE',
                'date' => $validated['date'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'attachment' => $attachmentUrl
            ]);

            // C. Actualizar Vehículo (Contador Salud)
            $vehicle->update([
                'last_service_date' => $validated['date'],
                'last_service_odometer' => $validated['current_odometer'] ?? $vehicle->odometer
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Mantenimiento registrado']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaintenanceSchedule(Request $request, $id)
    {
        try {
            $vehicle = auth()->user()->company->vehicles()->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'interval_km' => 'nullable|integer|min:1',
                'interval_days' => 'nullable|integer|min:1',
                'last_service_date' => 'required|date',
                'last_service_odometer' => 'required|integer',
            ]);

            $schedule = MaintenanceSchedule::create([
                'vehicle_id' => $vehicle->id,
                'name' => $validated['name'],

                // --- AQUÍ ESTABA EL ERROR ---
                // Usamos '?? null' para que si el frontend no manda el dato, no falle.
                'interval_km' => $validated['interval_km'] ?? null,
                'interval_days' => $validated['interval_days'] ?? null,

                'last_service_date' => $validated['last_service_date'],
                'last_service_odometer' => $validated['last_service_odometer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio programado correctamente',
                'data' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // OBTENER LOS RECORDATORIOS DE UN VEHÍCULO
    public function getMaintenanceSchedules($id)
    {
        $schedules = MaintenanceSchedule::where('vehicle_id', $id)
            ->where('is_active', true)
            ->get();

        return response()->json($schedules);
    }

    public function updateConfig(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        // Validamos que sea uno de los iconos permitidos
        $request->validate([
            'map_icon' => 'required|string|in:car-sport,bus,bicycle,boat,airplane,truck'
        ]);

        $vehicle->map_icon = $request->map_icon;
        $vehicle->save();

        return response()->json(['success' => true, 'message' => 'Icono actualizado']);
    }

    public function updateFromTraccar(Request $request)
    {
        // Traccar manda un JSON con 'position' y 'device'
        $data = $request->all();
        $uniqueId = $data['device']['uniqueId']; // El IMEI o ID que configuraste en Traccar

        // Buscamos el vehículo en TU base de datos por ese Identificador
        $vehicle = Vehicle::where('traccar_unique_id', $uniqueId)->first();

        if ($vehicle && isset($data['position'])) {
            $pos = $data['position'];

            $vehicle->update([
                'latitude' => $pos['latitude'],
                'longitude' => $pos['longitude'],
                'speed' => $pos['speed'] * 1.852, // Traccar manda nudos, convertimos a KM/H
                'heading' => $pos['course'],
                'last_update' => now(),
            ]);

            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'vehicle_not_found'], 404);
    }
}
