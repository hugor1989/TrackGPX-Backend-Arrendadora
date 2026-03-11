<?php

namespace App\Http\Controllers\Api\Geoference;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeofenceController extends Controller
{
    /**
     * Listar geocercas
     * GET /api/geofences
     */
    public function index()
    {
        try {
            // Obtenemos las geocercas de la empresa del usuario
            $geofences = auth()->user()->company->geofences()
                ->withCount('vehicles') // Contamos cuántos vehículos tienen asignados
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($geo) {
                    // TRANSFORMACIÓN DE DATOS
                    // Aquí agregamos los campos "virtuales" que el frontend espera
                    // pero que no existen en tu tabla SQL.
                    return [
                        'id' => $geo->id,
                        'name' => $geo->name,
                        'type' => $geo->type,
                        'coordinates' => $geo->coordinates, // El modelo ya lo convierte a array
                        'radius' => $geo->radius,
                        'vehicles_count' => $geo->vehicles_count,

                        // Campos virtuales para la UI
                        'description' => $geo->type === 'circle'
                            ? "Radio: " . number_format($geo->radius, 0) . "m"
                            : "Polígono personalizado",
                        'color' => '#226bfc', // Color azul por defecto para el mapa
                        'is_active' => true,  // Asumimos activo por defecto
                        'created_at' => $geo->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $geofences
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar geocercas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear geocerca
     * POST /api/geofences
     */
    public function store(Request $request)
    {
        try {
            // 1. Validaciones
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:circle,polygon',
                // Coordinates puede venir como array o JSON string
                'coordinates' => 'required',
                'radius' => 'nullable|numeric',
                // Validamos que si envían vehículos, existan en la BD
                'vehicle_ids' => 'nullable|array',
                'vehicle_ids.*' => 'exists:vehicles,id'
            ]);

            DB::beginTransaction();

            // 2. Crear la Geocerca (Solo campos de tu tabla geofences)
            $geofence = new Geofence();
            $geofence->company_id = auth()->user()->company_id;
            $geofence->name = $validated['name'];
            $geofence->type = $validated['type'];
            $geofence->coordinates = $validated['coordinates']; // El cast del modelo lo guarda como JSON
            $geofence->radius = $validated['radius'] ?? 0;
            $geofence->save();

            // 3. Asignar vehículos (Llenar tabla pivote geofence_vehicle)
            if (!empty($request->vehicle_ids)) {
                $geofence->vehicles()->sync($request->vehicle_ids);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Geocerca creada correctamente',
                'data' => $geofence
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
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar geocerca
     * DELETE /api/geofences/{id}
     */
    public function destroy($id)
    {
        try {
            // Buscamos dentro de la empresa para seguridad
            $geofence = auth()->user()->company->geofences()->findOrFail($id);

            // Al eliminar, la BD limpiará la tabla pivote automáticamente
            // gracias a ON DELETE CASCADE en tu migración.
            $geofence->delete();

            return response()->json([
                'success' => true,
                'message' => 'Geocerca eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar la geocerca'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $geofence = Geofence::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:circle,polygon',
            'coordinates' => 'required', // Puede ser objeto para círculo o array para polígono
            'radius' => 'nullable|numeric',
        ]);

        // Laravel 12 maneja automáticamente el casteo de JSON si lo tienes en el modelo
        $geofence->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'coordinates' => $validated['coordinates'],
            'radius' => $validated['radius'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $geofence
        ]);
    }
}
