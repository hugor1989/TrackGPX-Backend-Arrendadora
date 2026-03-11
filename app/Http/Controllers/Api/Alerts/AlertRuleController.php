<?php

namespace App\Http\Controllers\Api\Alerts;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlertRuleController extends Controller
{
    /**
     * Listar todas las reglas de alerta
     */
    public function index()
    {
        $rules = auth()->user()->company->alertRules()
            ->with(['geofence:id,name', 'vehicles:id,plate,brand,model']) // Eager loading optimizado
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $rules]);
    }

    /**
     * Crear nueva regla
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',

                // LISTA COMPLETA DE TIPOS PERMITIDOS
                'type' => 'required|in:geofence_enter,geofence_exit,overspeed,stop_duration,harsh_acceleration,harsh_braking,harsh_turn,power_cut,low_battery_vehicle,low_battery_device,sos_button,jamming,towing,door_open,ignition_on,ignition_off,sensor_fuel_drop,sensor_temperature,maintenance_due',

                // Validaciones condicionales inteligentes
                'geofence_id' => 'required_if:type,geofence_enter,geofence_exit|nullable|exists:geofences,id',

                // El valor es requerido para velocidad, temperatura, paradas, etc.
                'value' => 'required_if:type,overspeed,stop_duration,sensor_temperature,maintenance_due|nullable|numeric',

                'notification_settings' => 'required|array',
                'schedule_settings' => 'nullable|array', // Opcional

                'vehicle_ids' => 'required|array|min:1',
                'vehicle_ids.*' => 'exists:vehicles,id'
            ]);

            DB::beginTransaction();

            $rule = AlertRule::create([
                'company_id' => auth()->user()->company_id,
                'name' => $request->name,
                'type' => $request->type,
                'geofence_id' => $request->geofence_id,
                'value' => $request->value,
                'notification_settings' => $request->notification_settings,
                'schedule_settings' => $request->schedule_settings,
                'is_active' => true
            ]);

            $rule->vehicles()->sync($request->vehicle_ids);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Alerta configurada correctamente',
                'data' => $rule
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar regla
     */
    public function destroy($id)
    {
        try {
            $rule = auth()->user()->company->alertRules()->findOrFail($id);
            $rule->delete();
            return response()->json(['success' => true, 'message' => 'Regla eliminada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar'], 500);
        }
    }

    /**
     * Activar / Desactivar regla rápidamente
     */
    public function toggle($id)
    {
        try {
            $rule = auth()->user()->company->alertRules()->findOrFail($id);
            $rule->is_active = !$rule->is_active;
            $rule->save();

            return response()->json([
                'success' => true,
                'message' => $rule->is_active ? 'Alerta activada' : 'Alerta desactivada',
                'data' => $rule
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar'], 500);
        }
    }
}
