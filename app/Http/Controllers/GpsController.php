<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\Position;
use App\Models\Device;
use Illuminate\Support\Facades\Log;
use App\Events\DevicePositionUpdated;

class GpsController extends Controller
{
    public function receivePosition(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data) || !isset($data['position'], $data['device'])) {
            Log::warning('Position body inválido', ['raw' => $request->getContent()]);
            return response()->json(['error' => 'incomplete'], 400);
        }

        $pos      = $data['position'];
        $uniqueId = $data['device']['uniqueId'] ?? null;

        if (!$uniqueId || !isset($pos['latitude'], $pos['longitude'])) {
            Log::warning('Posición incompleta', $data);
            return response()->json(['error' => 'incomplete'], 400);
        }

        // Ahora puedes buscar por IMEI, ya no necesitas traccar_id
        $device = Device::where('imei', $uniqueId)->first();
        if (!$device) {
            Log::error("Dispositivo no encontrado IMEI: {$uniqueId}");
            return response()->json(['error' => 'not found'], 404);
        }

        $newPosition = Position::create([
            'device_id'  => $device->id,
            'vehicle_id' => $device->vehicle_id,
            'latitude'   => $pos['latitude'],
            'longitude'  => $pos['longitude'],
            'altitude'   => $pos['altitude'] ?? 0,
            'speed'      => ($pos['speed'] ?? 0) * 1.852,
            'heading'    => $pos['course'] ?? 0,
            'accuracy'   => $pos['accuracy'] ?? 0,
            'ignition'   => $pos['attributes']['ignition'] ?? false,
            'attributes' => json_encode($pos['attributes'] ?? []),
            'address'    => $pos['address'] ?? null,
            'timestamp' => isset($pos['serverTime'])
                ? date('Y-m-d H:i:s', strtotime($pos['serverTime']))
                : now(),
        ]);

        // Después de guardar la posición
        $companyId = $device->vehicle->company_id
            ?? $device->company_id;

        broadcast(new DevicePositionUpdated(
            company_id: $companyId,
            vehicle_id: $device->vehicle_id,
            latitude: $pos['latitude'],
            longitude: $pos['longitude'],
            speed: $newPosition->speed,
            heading: $pos['course'] ?? 0,
            ignition: $pos['attributes']['ignition'] ?? false,
            timestamp: $newPosition->timestamp,
        ))->toOthers();

        if ($device->vehicle) {
            $device->vehicle->update([
                'last_latitude'  => $pos['latitude'],
                'last_longitude' => $pos['longitude'],
                'last_update'    => now(),
            ]);
        }

        Log::info("Posición guardada", [
            'imei'  => $uniqueId,
            'lat'   => $pos['latitude'],
            'lng'   => $pos['longitude'],
            'speed' => $pos['speed'] ?? 0,
        ]);

        return response()->json(['status' => 'stored', 'id' => $newPosition->id]);
    }

    public function receiveEvent(Request $request)
    {
        $raw = $request->getContent();
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            Log::warning('Event body inválido', ['raw' => $raw]);
            return response()->json(['error' => 'invalid body'], 400);
        }

        $eventType = $data['event']['type']     ?? null;
        $uniqueId  = $data['device']['uniqueId'] ?? null;
        $position  = $data['position']           ?? null;

        Log::info("Evento Traccar: {$eventType}", ['uniqueId' => $uniqueId]);

        $device = Device::where('imei', $uniqueId)->first();
        if (!$device) {
            Log::error("Dispositivo no encontrado IMEI: {$uniqueId}");
            return response()->json(['error' => 'not found'], 404);
        }

        switch ($eventType) {
            case 'deviceOverspeed':
                // dispatch(new SpeedAlertJob($device, $position));
                break;

            case 'geofenceEnter':
            case 'geofenceExit':
                $geofenceId = $data['event']['geofenceId'] ?? null;
                // dispatch(new GeofenceAlertJob($device, $geofenceId, $eventType));
                break;

            case 'alarm':
                $alarm = $data['event']['attributes']['alarm'] ?? 'unknown';
                // dispatch(new AlarmJob($device, $alarm, $position));
                break;

            case 'deviceOnline':
                $device->update(['status' => 'active', 'last_update' => now()]);
                break;

            case 'deviceOffline':
                $device->update(['status' => 'inactive', 'last_update' => now()]);
                break;
        }

        return response()->json(['ok' => true]);
    }
}
