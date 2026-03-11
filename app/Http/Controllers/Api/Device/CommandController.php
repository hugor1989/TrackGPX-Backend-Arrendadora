<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Services\TraccarCommandService;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CommandController extends Controller
{
    public function __construct(private TraccarCommandService $traccar) {}

    public function send(Request $request, $deviceId)
    {
        $request->validate([
            'command' => 'required|in:engineStop,engineResume,positionSingle,reboot'
        ]);

        // Verificar que el dispositivo pertenece a la empresa del usuario
        $device = auth()->user()->company->devices()->findOrFail($deviceId);

        // Necesitas el traccar_id — lo buscamos por IMEI en Traccar
        $traccarId = $this->getTraccarId($device->imei);
        if (!$traccarId) {
            return response()->json(['error' => 'Dispositivo no encontrado en Traccar'], 404);
        }

        $result = match($request->command) {
            'engineStop'     => $this->traccar->engineStop($traccarId),
            'engineResume'   => $this->traccar->engineResume($traccarId),
            'positionSingle' => $this->traccar->requestPosition($traccarId),
            'reboot'         => $this->traccar->reboot($traccarId),
        };

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json(['success' => true, 'message' => 'Comando enviado']);
    }

    private function getTraccarId(string $imei): ?int
    {
        $response = Http::withBasicAuth(
            config('services.traccar.user'),
            config('services.traccar.pass')
        )->get(config('services.traccar.url') . '/api/devices', [
            'uniqueId' => $imei
        ]);

        $devices = $response->json();
        return $devices[0]['id'] ?? null;
    }
}