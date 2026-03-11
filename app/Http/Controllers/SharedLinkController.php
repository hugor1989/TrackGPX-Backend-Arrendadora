<?php

namespace App\Models;

namespace App\Http\Controllers;

use App\Models\SharedLink;
use App\Models\Position;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Controllers\AppBaseController;

class SharedLinkController extends AppBaseController
{
    /**
     * PASO 1: Generar el link desde el panel de administración
     */
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'hours' => 'required|integer|min:1', // Cuántas horas durará vivo el link
        ]);

        // Crear el registro en la BD
        $sharedLink = SharedLink::create([
            'vehicle_id' => $request->vehicle_id,
            'token' => SharedLink::generateUniqueToken(),
            'expires_at' => Carbon::now()->addHours($request->hours),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Link generado con éxito',
            'url' => url("/track-live/{$sharedLink->token}"),
            'token' => $sharedLink->token
        ]);
    }

    /**
     * PASO 2: La API que consultará el Mapa Público (el que ve el cliente)
     */
    // En app/Http/Controllers/SharedLinkController.php

    public function getPublicLocation($token)
    {
        $link = SharedLink::where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$link) {
            return response()->json(['error' => 'El enlace ha expirado o no existe'], 404);
        }

        $vehicle = $link->vehicle;

        // Última posición del vehículo
        $lastPosition = Position::where('vehicle_id', $vehicle->id)
            ->latest('timestamp')
            ->first();

        return response()->json([
            'name'        => $vehicle->name,
            'plate'       => $vehicle->plate,
            'lat'         => $lastPosition->latitude ?? null,
            'lng'         => $lastPosition->longitude ?? null,
            'speed'       => $lastPosition->speed ?? 0,
            'heading'     => $lastPosition->heading ?? 0,
            'map_icon'    => $vehicle->map_icon,
            'last_update' => $lastPosition
                ? \Carbon\Carbon::parse($lastPosition->timestamp)->diffForHumans()
                : 'Sin señal'
        ]);
    }
}
