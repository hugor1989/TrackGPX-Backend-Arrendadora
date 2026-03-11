<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleExpense;
use Carbon\Carbon;

class ExpenseSeeder extends Seeder
{
    public function run()
    {
        $vehicleId = 6; // Tu Chevrolet JRV1138

        // 1. Un Seguro (Gasto Fuerte)
        VehicleExpense::create([
            'vehicle_id' => $vehicleId,
            'date' => Carbon::now()->startOfMonth(),
            'type' => 'INSURANCE',
            'amount' => 12500.00,
            'description' => 'Renovación Póliza Anual'
        ]);

        // 2. Mantenimiento Preventivo
        VehicleExpense::create([
            'vehicle_id' => $vehicleId,
            'date' => Carbon::now()->subDays(5),
            'type' => 'MAINTENANCE',
            'amount' => 3200.50,
            'description' => 'Servicio Mayor 50,000 km'
        ]);

        // 3. Varios gastos de Combustible
        for ($i = 0; $i < 5; $i++) {
            VehicleExpense::create([
                'vehicle_id' => $vehicleId,
                'date' => Carbon::now()->subDays(rand(1, 20)),
                'type' => 'FUEL',
                'amount' => rand(800, 1500),
                'description' => 'Carga Premium'
            ]);
        }

        // 4. Una Multa (Para que duela ver el reporte)
        VehicleExpense::create([
            'vehicle_id' => $vehicleId,
            'date' => Carbon::now()->subDays(2),
            'type' => 'FINE',
            'amount' => 2400.00,
            'description' => 'Exceso Velocidad - Foto Cívica'
        ]);
    }
}