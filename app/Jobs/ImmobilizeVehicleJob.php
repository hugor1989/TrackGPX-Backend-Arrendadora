<?php

namespace App\Jobs;

use App\Models\Vehicle;
use App\Models\LeaseContract; // Importante para actualizar el estado
use App\Services\TraccarCommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImmobilizeVehicleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Añadimos $action para saber si es stop o resume
    public function __construct(
        protected $vehicleId, 
        protected $reason,
        protected $action = 'stop' 
    ) {}

    public function handle(TraccarCommandService $traccarService): void
    {
        $vehicle = Vehicle::with(['device', 'lastPosition'])->find($this->vehicleId);

        if (!$vehicle || !$vehicle->device) {
            Log::error("Job Fallido: Vehículo o dispositivo no encontrado para ID {$this->vehicleId}");
            return;
        }

        // --- LÓGICA DE SEGURIDAD (Solo aplica para bloqueo) ---
        if ($this->action === 'stop') {
            if ($vehicle->lastPosition && $vehicle->lastPosition->speed > 10) {
                Log::info("Vehículo {$vehicle->plate} a alta velocidad ({$vehicle->lastPosition->speed} km/h). Reintentando en 5 min.");
                $this->release(300); // Reintenta en 5 minutos
                return;
            }
        }

        $traccarId = $vehicle->device->traccar_id;

        if (!$traccarId) {
            Log::error("El dispositivo IMEI {$vehicle->device->imei} no tiene traccar_id.");
            return;
        }

        // --- EJECUCIÓN DINÁMICA ---
        if ($this->action === 'stop') {
            $result = $traccarService->engineStop($traccarId);
            $targetStatus = true;
            $type = 'engineStop';
        } else {
            $result = $traccarService->engineResume($traccarId);
            $targetStatus = false;
            $type = 'engineResume';
        }

        // REGISTRO EN LOG (Guardamos el intento)
        \App\Models\CommandLog::create([
            'vehicle_id'   => $this->vehicleId,
            'user_id'      => null, // null indica que fue una tarea automática del sistema
            'command_type' => $type,
            'action'       => $this->action,
            'reason'       => $this->reason,
            'status'       => $result['success'],
            'error_message' => $result['success'] ? null : ($result['error'] ?? 'Error desconocido'),
            'metadata'     => $result['data'] ?? null,
        ]);
        if (!$result['success']) {
            Log::error("Error enviando comando ({$this->action}) a Traccar: " . $result['error']);
            // Opcional: Reintentar si falla la conexión con el servidor Traccar
            return;
        }

        // --- ACTUALIZACIÓN DE CONTRATO ---
        // Buscamos el contrato activo de este vehículo para marcar el estado real
        $contract = LeaseContract::where('vehicle_id', $this->vehicleId)
            ->whereIn('status', ['active', 'past_due'])
            ->first();

        if ($contract) {
            $contract->update(['is_immobilized' => $targetStatus]);
        }

        Log::info("Vehículo {$vehicle->plate} comando {$this->action} exitoso. Motivo: {$this->reason}");
    }
}