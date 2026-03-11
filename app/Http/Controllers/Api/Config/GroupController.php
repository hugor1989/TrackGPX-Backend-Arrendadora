<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use App\Models\Account;

class GroupController extends Controller
{
    /**
     * Listar Grupos con su Supervisor y Conteo de Vehículos
     */
    public function index()
    {
        $user = auth()->user();
        
        $groups = Group::where('company_id', $user->company_id)
            ->with('supervisor:id,name,email') // Traemos datos básicos del supervisor
            ->withCount('vehicles') // Cuenta cuántos carros hay en el grupo
            ->get();

        return response()->json($groups);
    }

    /**
     * Crear un nuevo Grupo
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'supervisor_id' => 'nullable|exists:accounts,id', // Validamos que el usuario exista
            'color' => 'nullable|string|max:7'
        ]);

        $group = Group::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'supervisor_id' => $request->supervisor_id,
            'color' => $request->color ?? '#3b82f6',
        ]);

        return response()->json(['success' => true, 'data' => $group]);
    }

    /**
     * Actualizar Grupo
     */
    public function update(Request $request, $id)
    {
        $group = Group::where('company_id', auth()->user()->company_id)->findOrFail($id);
        
        $group->update($request->only(['name', 'supervisor_id', 'color', 'description']));

        return response()->json(['success' => true, 'data' => $group]);
    }

    /**
     * Eliminar Grupo
     */
    public function destroy($id)
    {
        $group = Group::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $group->delete(); 
        // Los vehículos quedarán huérfanos (group_id = null) automáticamente por la BD.

        return response()->json(['success' => true, 'message' => 'Grupo eliminado']);
    }

    /**
     * Asignar Vehículos al Grupo (Movimiento Masivo)
     * Recibe: { "vehicle_ids": [1, 4, 10] }
     */
    public function assignVehicles(Request $request, $groupId)
    {
        $request->validate([
            'vehicle_ids' => 'required|array',
            'vehicle_ids.*' => 'exists:vehicles,id'
        ]);

        $user = auth()->user();

        // 1. Validar que el grupo pertenezca a la empresa
        $group = Group::where('company_id', $user->company_id)->findOrFail($groupId);

        // 2. Actualizar solo los vehículos de esta empresa
        Vehicle::whereIn('id', $request->vehicle_ids)
            ->where('company_id', $user->company_id)
            ->update(['group_id' => $groupId]);

        return response()->json([
            'success' => true, 
            'message' => 'Vehículos asignados correctamente al grupo ' . $group->name
        ]);
    }

    /**
     * Obtener lista de Fleet Managers activos para asignar como supervisores
     */
    public function getSupervisors()
    {
        $users = Account::where('company_id', auth()->user()->company_id)
            ->where('role', 'fleet_manager') // <--- SOLO TRAEMOS ESTE ROL
            ->where('status', 'active')      // Y que estén activos
            ->select('id', 'name', 'email', 'role')
            ->get();

        return response()->json($users);
    }
}