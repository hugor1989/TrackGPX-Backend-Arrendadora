<?php

namespace App\Http\Controllers\Api\Scraping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\Fine;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\ScrapingLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScrapingWebhookController extends Controller
{
    public function receiveFines(Request $request)
    {
        $request->validate([
            'plate' => 'required|string',
            'state' => 'required|string',
            'fines' => 'array'
        ]);

        $plate = strtoupper(str_replace(['-', ' '], '', $request->plate));
        $vehicle = Vehicle::where('plate', $plate)->first();

        if (!$vehicle) {
            return response()->json(['message' => 'Vehículo no encontrado'], 404);
        }

        DB::beginTransaction();
        try {
            $newFinesCount = 0;

            // 1. Log de Scraping (Auditoría)
            ScrapingLog::create([
                'vehicle_id' => $vehicle->id,
                'state' => $request->state,
                'action' => 'fines_check',
                'result' => empty($request->fines) ? 'success' : 'success_with_data',
                'raw_data' => json_encode($request->all()),
                'executed_at' => now()
            ]);

            // 2. Procesar Multas
            foreach ($request->fines as $fineData) {

                $montoLimpio = str_replace(['$', ','], '', $fineData['monto']);

                try {
                    $fechaString = $fineData['fecha'] . ' ' . $fineData['hora'];
                    $fechaDetectada = Carbon::createFromFormat('d/m/Y H:i:s', $fechaString);
                } catch (\Exception $e) {
                    $fechaDetectada = now();
                }

                // Guardar la Multa (Detalle financiero)
                // OJO: Asegúrate de haber creado la tabla 'fines' (esa SÍ la necesitas)
                $fine = Fine::updateOrCreate(
                    ['vehicle_id' => $vehicle->id, 'reference' => $fineData['folio']],
                    [
                        'company_id' => $vehicle->company_id,
                        'source' => $request->state,
                        'description' => $fineData['motivo'],
                        'amount' => $montoLimpio,
                        'status' => (strtolower($fineData['estatus']) === 'pagada') ? 'paid' : 'pending',
                        'detected_at' => $fechaDetectada,
                        'updated_at' => now()
                    ]
                );

                // 3. Generar ALERTA (Notificación) usando 'alert_logs'
                if ($fine->wasRecentlyCreated && $fine->status === 'pending') {

                    AlertLog::create([
                        'company_id' => $vehicle->company_id,
                        'vehicle_id' => $vehicle->id,
                        'alert_rule_id' => null, // Es null porque no viene de una regla de GPS
                        'type' => 'FINE_DETECTED', // Tu tabla permite varchar, así que esto es válido
                        'message' => "Multa detectada ({$plate}): $" . number_format($montoLimpio, 2),
                        'latitude' => null, // No aplica
                        'longitude' => null, // No aplica
                        'speed' => 0,
                        'occurred_at' => now(),
                        'is_read' => 0
                    ]);

                    $newFinesCount++;
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Procesado. {$newFinesCount} multas nuevas."]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
