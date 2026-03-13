<?php

namespace App\Jobs;

use App\Models\Vehicle;
use App\Models\CommandLog;
use App\Services\TraccarCommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivateRecoveryModeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de reintentos si falla la conexión con Traccar.
     */
    public $tries = 3;

    public function __construct(protected int $vehicleId) {}

    public function handle(TraccarCommandService $traccarService): void
    {
        $vehicle = Vehicle::with('device')->find($this->vehicleId);

        if (!$vehicle || !$vehicle->device) {
            Log::error("No se pudo activar Modo Recuperación: Vehículo o Dispositivo no encontrado.", ['id' => $this->vehicleId]);
            return;
        }

        // Enviamos el comando de frecuencia de 10 segundos (Modo Recuperación) [cite: 236]
        $result = $traccarService->setRecoveryFrequency($vehicle->device->traccar_id, 10);

        // Registramos la acción para la auditoría de la financiera [cite: 99]
        CommandLog::create([
            'vehicle_id'   => $this->vehicleId,
            'command_type' => 'custom',
            'action'       => 'AUTO_RECOVERY_MODE_ON',
            'reason'       => 'Activación automática por alerta de riesgo detectada',
            'status'       => $result['success'] ? 'success' : 'failed',
            'response'     => json_encode($result)
        ]);

        if (!$result['success']) {
            Log::error("Fallo al enviar Modo Recuperación al dispositivo {$vehicle->device->traccar_id}");
            // Esto hará que el Job se reintente según la variable $tries
            $this->release(60); 
        } else {
            Log::info("Modo Recuperación activado exitosamente para unidad: {$vehicle->plate}");
        }
    }
}