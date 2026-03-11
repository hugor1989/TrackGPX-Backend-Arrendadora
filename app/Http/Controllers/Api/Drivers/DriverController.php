<?php

namespace App\Http\Controllers\Api\Drivers;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile as Driver;
use App\Models\Account;
use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DriverController extends Controller
{
    /**
     * Obtener todos los conductores de la empresa
     * GET /api/drivers
     */
    public function index()
    {
        try {
            $drivers = auth()->user()->company->drivers()
                ->with([
                    'account',
                    'currentVehicle' => function($query) {
                        // ✅ SOLUCIÓN: Especificar la tabla 'vehicles.' antes de cada columna
                        $query->select(
                            'vehicles.id', 
                            'vehicles.name', 
                            'vehicles.plate', 
                            'vehicles.brand', 
                            'vehicles.model'
                        );
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $drivers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conductores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener conductores disponibles (sin vehículo asignado)
     * GET /api/drivers/available
     */
    public function available()
    {
        try {
            // Obtener IDs de conductores con asignación activa
            $assignedDriverIds = VehicleAssignment::where('active', true)
                ->pluck('driver_id')
                ->toArray();

            $drivers = auth()->user()->company->drivers()
                ->with('account')
                ->whereNotIn('id', $assignedDriverIds)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $drivers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conductores disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un conductor específico
     * GET /api/drivers/{id}
     */
    public function show($id)
    {
        try {
            $driver = auth()->user()->company->drivers()
                ->with([
                    'account',
                    'currentVehicle',
                    'vehicleHistory' => function($query) {
                        $query->with('vehicle')->orderBy('assigned_from', 'desc')->limit(10);
                    }
                ])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $driver
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conductor no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear un nuevo conductor
     * POST /api/drivers
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:accounts,email',
                'phone' => 'nullable|string|max:20',
                'license_number' => 'nullable|string|max:50',
                'emergency_contact' => 'nullable|string|max:100',
                'password' => 'nullable|string|min:8',
            ]);

            DB::beginTransaction();

            // Crear cuenta de usuario
            $account = Account::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password'] ?? 'password123'), // Password por defecto
                'account_type' => 'driver',
                'company_id' => auth()->user()->company_id,
            ]);

            // Crear conductor
            $driver = Driver::create([
                'account_id' => $account->id,
                'company_id' => auth()->user()->company_id,
                'license_number' => $validated['license_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'emergency_contact' => $validated['emergency_contact'] ?? null,
            ]);

            DB::commit();

            // Recargar con relaciones
            $driver->load('account');

            return response()->json([
                'success' => true,
                'message' => 'Conductor creado correctamente',
                'data' => $driver
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
                'message' => 'Error al crear conductor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un conductor
     * PUT /api/drivers/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $driver = auth()->user()->company->drivers()->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:accounts,email,' . $driver->account_id,
                'phone' => 'nullable|string|max:20',
                'license_number' => 'nullable|string|max:50',
                'emergency_contact' => 'nullable|string|max:100',
                'password' => 'nullable|string|min:8',
            ]);

            DB::beginTransaction();

            // Actualizar cuenta
            $accountData = [];
            if (isset($validated['name'])) {
                $accountData['name'] = $validated['name'];
            }
            if (isset($validated['email'])) {
                $accountData['email'] = $validated['email'];
            }
            if (isset($validated['password'])) {
                $accountData['password'] = Hash::make($validated['password']);
            }

            if (!empty($accountData)) {
                $driver->account->update($accountData);
            }

            // Actualizar conductor
            $driverData = [];
            if (isset($validated['phone'])) {
                $driverData['phone'] = $validated['phone'];
            }
            if (isset($validated['license_number'])) {
                $driverData['license_number'] = $validated['license_number'];
            }
            if (isset($validated['emergency_contact'])) {
                $driverData['emergency_contact'] = $validated['emergency_contact'];
            }

            if (!empty($driverData)) {
                $driver->update($driverData);
            }

            DB::commit();

            // Recargar con relaciones
            $driver->load('account');

            return response()->json([
                'success' => true,
                'message' => 'Conductor actualizado correctamente',
                'data' => $driver
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
                'message' => 'Error al actualizar conductor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un conductor
     * DELETE /api/drivers/{id}
     */
    public function destroy($id)
    {
        try {
            $driver = auth()->user()->company->drivers()->findOrFail($id);

            DB::beginTransaction();

            // Finalizar asignaciones activas
            VehicleAssignment::where('driver_id', $driver->id)
                ->where('active', true)
                ->update([
                    'assigned_to' => now(),
                    'active' => false,
                ]);

            // Actualizar vehículos que tengan este conductor asignado
            auth()->user()->company->vehicles()
                ->where('driver_id', $driver->id)
                ->update(['driver_id' => null]);

            // Eliminar conductor
            $account = $driver->account;
            $driver->delete();
            $account->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conductor eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar conductor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}