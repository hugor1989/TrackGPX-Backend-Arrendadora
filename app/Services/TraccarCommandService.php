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

    /**
     * Motor central de envío de comandos
     */
    public function sendCommand(int $traccarDeviceId, string $type, array $attributes = []): array
    {
        try {
            $response = Http::withBasicAuth($this->user, $this->pass)
                ->timeout(15) 
                ->retry(2, 200) 
                ->post("{$this->baseUrl}/api/commands/send", [
                    'deviceId'   => $traccarDeviceId,
                    'type'       => $type,
                    'attributes' => (object) $attributes,
                ]);

            if ($response->successful()) {
                Log::info("Comando enviado: {$type}", ['deviceId' => $traccarDeviceId]);
                return ['success' => true, 'data' => $response->json()];
            }

            if ($response->status() === 400) {
                return ['success' => false, 'error' => 'Dispositivo fuera de línea o comando no soportado'];
            }

            Log::error("Error en servidor Traccar: {$type}", ['status' => $response->status()]);
            return ['success' => false, 'error' => "Error servidor Traccar: " . $response->status()];
        } catch (\Exception $e) {
            Log::error("Excepción en TraccarService: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // --- COMANDOS ESTÁNDAR ---

    public function engineStop(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'engineStop');
    }

    public function engineResume(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'engineResume');
    }

    // --- COMANDOS ESPECÍFICOS VL103M (Protocolo Jimi/Concox) ---

    /**
     * Modo Recuperación: Tracking cada X segundos 
     * TIMER,ON,10,3600# -> 10s mov, 1h detenido
     */
    public function setRecoveryFrequency(int $traccarDeviceId, int $seconds = 10): array
    {
        return $this->sendCommand($traccarDeviceId, 'custom', [
            'data' => "TIMER,ON,{$seconds},3600#"
        ]);
    }

    /**
     * Modo Normal: Restaura frecuencia estándar para ahorro de datos
     */
    public function setNormalFrequency(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'custom', [
            'data' => "TIMER,ON,180,3600#" // 3 minutos en movimiento
        ]);
    }

    /**
     * Alerta de Jammer: Activa la detección de bloqueadores de señal [cite: 105, 296]
     */
    public function enableJammerDetection(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'custom', [
            'data' => "JAMMER,1,10,10#" // Sensibilidad estándar
        ]);
    }

    /**
     * Sensibilidad de Vibración: Para detectar si el auto es golpeado o remolcado 
     */
    public function setVibrationAlarm(int $traccarDeviceId, int $level = 2): array
    {
        return $this->sendCommand($traccarDeviceId, 'custom', [
            'data' => "SENALM,ON,{$level}#"
        ]);
    }

    /**
     * Reinicio de Hardware: Útil si el GPS se queda "congelado"
     */
    public function reboot(int $traccarDeviceId): array
    {
        return $this->sendCommand($traccarDeviceId, 'custom', [
            'data' => "RESET#"
        ]);
    }
}