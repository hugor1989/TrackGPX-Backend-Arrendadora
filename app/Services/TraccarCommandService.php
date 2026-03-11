<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TraccarCommandService
{
    private string $baseUrl;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->baseUrl = config('services.traccar.url', 'http://traccar:8082');
        $this->user    = config('services.traccar.user');
        $this->pass    = config('services.traccar.pass');
    }

    public function sendCommand(int $traccarDeviceId, string $type, array $attributes = []): array
    {
        try {
            $response = Http::withBasicAuth($this->user, $this->pass)
                ->post("{$this->baseUrl}/api/commands/send", [
                    'deviceId'   => $traccarDeviceId,
                    'type'       => $type,
                    'attributes' => (object) $attributes, // ← cast a objeto, no array
                ]);

            if ($response->successful()) {
                Log::info("Comando enviado: {$type}", ['deviceId' => $traccarDeviceId]);
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error("Error enviando comando: {$type}", ['response' => $response->body()]);
            return ['success' => false, 'error' => $response->body()];

        } catch (\Exception $e) {
            Log::error("Excepción enviando comando: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Cortar motor
    public function engineStop(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'engineStop');
    }

    // Reactivar motor
    public function engineResume(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'engineResume');
    }

    // Solicitar posición ahora
    public function requestPosition(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'positionSingle');
    }

    // Reiniciar dispositivo
    public function reboot(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'reboot');
    }
}
