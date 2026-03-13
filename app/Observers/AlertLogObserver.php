<?php

namespace App\Observers;

use App\Models\AlertLog;
use App\Services\TraccarCommandService;
use App\Jobs\ActivateRecoveryModeJob; // Es mejor usar un Job para no bloquear el proceso
use Illuminate\Support\Facades\Log;

class AlertLogObserver
{
    /**
     * Se ejecuta cada vez que se inserta una nueva alerta.
     */
    public function created(AlertLog $alertLog): void
    {
        // Definimos los tipos de alerta que activan el Modo Recuperación según el documento [cite: 104, 105, 109, 293]
        $criticalAlerts = [
            'power_cut',          // Batería desconectada [cite: 104]
            'jamming',            // Intento de inhibición de señal [cite: 105, 122]
            'low_battery_device', // GPS a punto de apagarse [cite: 65]
            'towing'              // Vehículo siendo remolcado [cite: 111]
        ];

        // También revisamos si la geocerca es de tipo 'border' o 'danger' [cite: 84, 86, 281, 282]
        $isCriticalGeofence = false;
        if ($alertLog->alertRule && $alertLog->alertRule->geofence) {
            $isCriticalGeofence = in_array($alertLog->alertRule->geofence->category, ['border', 'danger']);
        }

        if (in_array($alertLog->type, $criticalAlerts) || $isCriticalGeofence) {
            Log::warning("ALERTA CRÍTICA DETECTADA: Activando Modo Recuperación automático para Vehículo ID: {$alertLog->vehicle_id}");

            // Disparamos el Job para cambiar la frecuencia a 10 segundos [cite: 236]
            // Usamos un Job por si el servidor de Traccar tarda en responder
            ActivateRecoveryModeJob::dispatch($alertLog->vehicle_id);
        }
    }
}