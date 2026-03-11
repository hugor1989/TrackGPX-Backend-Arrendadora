<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Listar todos los planes
     * 
     * GET /api/plans
     */
    public function index(Request $request)
    {
        try {
            $plans = Plan::active()
                ->when($request->interval, function ($query) use ($request) {
                    $query->where('interval', $request->interval);
                })
                ->orderBy('price', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $plans,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un plan específico
     * 
     * GET /api/plans/{id}
     */
    public function show(int $id)
    {
        try {
            $plan = Plan::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado',
            ], 404);
        }
    }

    /**
     * Crear un nuevo plan (ADMIN)
     * 
     * POST /api/plans
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'interval' => 'required|in:day,week,month,year',
            'interval_count' => 'nullable|integer|min:1',
            'max_vehicles' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
            'sat_product_code' => 'nullable|string|max:20',
        ]);

        try {
            $plan = Plan::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Plan creado exitosamente',
                'data' => $plan->fresh(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un plan (ADMIN)
     * 
     * PUT /api/plans/{id}
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'max_vehicles' => 'sometimes|required|integer|min:1',
            'features' => 'nullable|array',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        try {
            $plan = Plan::findOrFail($id);

            // No permitir cambiar precio o intervalo (OpenPay no lo permite)
            $allowedFields = $request->only([
                'name',
                'description',
                'max_vehicles',
                'features',
                'status',
            ]);

            $plan->update($allowedFields);

            return response()->json([
                'success' => true,
                'message' => 'Plan actualizado exitosamente',
                'data' => $plan->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un plan (ADMIN)
     * 
     * DELETE /api/plans/{id}
     */
    public function destroy(int $id)
    {
        try {
            $plan = Plan::findOrFail($id);

            // El Observer manejará la lógica de validación
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plan eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar plan: ' . $e->getMessage(),
            ], 400);
        }
    }
}