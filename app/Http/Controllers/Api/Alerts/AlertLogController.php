<?php

namespace App\Http\Controllers\Api\Alerts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AlertLog;

class AlertLogController extends Controller
{
    /**
     * Obtener historial de alertas (Paginado para rendimiento)
     */
    public function index(Request $request)
    {
        $query = auth()->user()->company->alertLogs()
            ->with(['vehicle:id,plate,brand,model']) // Traemos datos del vehículo
            ->orderBy('occurred_at', 'desc');

        // Filtros opcionales (Para tus botones de filtro en el sidebar)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        
        if ($request->has('unread_only') && $request->unread_only == 'true') {
            $query->where('is_read', false);
        }

        // Retornamos 20 por página para scroll infinito
        $logs = $query->paginate(20);

        return response()->json($logs);
    }

    /**
     * Marcar alerta como leída (Para cuando el monitorista la revisa)
     */
    public function markAsRead($id)
    {
        $log = auth()->user()->company->alertLogs()->findOrFail($id);
        $log->update(['is_read' => true]);
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Marcar TODAS como leídas
     */
    public function markAllAsRead()
    {
        auth()->user()->company->alertLogs()
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
        return response()->json(['success' => true]);
    }
}