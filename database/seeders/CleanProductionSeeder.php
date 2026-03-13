<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;
use App\Models\{
    Company, Vehicle, Device, SimCard, Account, CustomerProfile,
    LeaseContract, LeasePayment, VehicleAssignment, Position,
    Fine, AlertRule, AlertLog, CustomerRiskScore, Geofence
};

class CleanProductionSeeder extends Seeder
{
    private int $companyId = 2;

    // Coordenadas reales de Guadalajara y ZMG
    private array $zones = [
        ['name' => 'GDL Centro',       'lat' => 20.6744,  'lng' => -103.3873],
        ['name' => 'Zapopan',          'lat' => 20.7209,  'lng' => -103.3887],
        ['name' => 'Tlaquepaque',      'lat' => 20.6423,  'lng' => -103.3176],
        ['name' => 'Tonalá',           'lat' => 20.6236,  'lng' => -103.2344],
        ['name' => 'Tlajomulco',       'lat' => 20.4724,  'lng' => -103.4393],
        ['name' => 'El Salto',         'lat' => 20.5387,  'lng' => -103.1957],
        ['name' => 'Periférico Norte', 'lat' => 20.7512,  'lng' => -103.3612],
        ['name' => 'Plaza del Sol',    'lat' => 20.6432,  'lng' => -103.4118],
    ];

    private array $vehicleModels = [
        ['brand' => 'Nissan',     'model' => 'Versa',    'type' => 'car'],
        ['brand' => 'Nissan',     'model' => 'Sentra',   'type' => 'car'],
        ['brand' => 'Chevrolet',  'model' => 'Aveo',     'type' => 'car'],
        ['brand' => 'Chevrolet',  'model' => 'Beat',     'type' => 'car'],
        ['brand' => 'Volkswagen', 'model' => 'Vento',    'type' => 'car'],
        ['brand' => 'Toyota',     'model' => 'Yaris',    'type' => 'car'],
        ['brand' => 'Kia',        'model' => 'Rio',      'type' => 'car'],
        ['brand' => 'Hyundai',    'model' => 'Grand i10','type' => 'car'],
        ['brand' => 'Seat',       'model' => 'Ibiza',    'type' => 'car'],
        ['brand' => 'Renault',    'model' => 'Logan',    'type' => 'car'],
    ];

    public function run(): void
    {
        $faker = Faker::create('es_MX');

        DB::beginTransaction();
        try {
            $this->command->info('🧹 Limpiando datos anteriores...');
            $this->cleanup();

            $this->command->info('🏗️  Creando infraestructura base...');
            [$geofences, $rules] = $this->createInfrastructure($faker);

            $this->command->info('👥 Creando 30 clientes con contratos...');
            $this->createClients($faker, $geofences, $rules);

            // ── Vehículos libres (sin arrendatario) ──────────────────────
            $this->command->info('🚗 Creando 5 vehículos disponibles...');
            $this->createFreeVehicles($faker, $rules);

            DB::commit();
            $this->command->info('✅ Seeder completado. 30 clientes + 5 vehículos libres generados.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LIMPIEZA
    // ─────────────────────────────────────────────────────────────────────────
    private function cleanup(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $customerIds = Account::where('company_id', $this->companyId)
            ->where('role', 'customer')->pluck('id');
        $vIds = Vehicle::where('company_id', $this->companyId)->pluck('id');

        AlertLog::where('company_id', $this->companyId)->delete();
        DB::table('alert_rule_vehicle')->whereIn('vehicle_id', $vIds)->delete();
        AlertRule::where('company_id', $this->companyId)->delete();
        Geofence::where('company_id', $this->companyId)->delete();
        DB::table('lease_payments')->whereIn(
            'lease_contract_id',
            LeaseContract::whereIn('account_id', $customerIds)->pluck('id')
        )->delete();
        LeaseContract::whereIn('account_id', $customerIds)->delete();
        VehicleAssignment::whereIn('account_id', $customerIds)->delete();
        CustomerProfile::whereIn('account_id', $customerIds)->delete();
        CustomerRiskScore::whereIn('account_id', $customerIds)->delete();
        Position::whereIn('vehicle_id', $vIds)->delete();
        Fine::whereIn('vehicle_id', $vIds)->delete();
        Device::where('company_id', $this->companyId)->delete();
        SimCard::where('company_id', $this->companyId)->delete();
        Vehicle::where('company_id', $this->companyId)->delete();
        Account::whereIn('id', $customerIds)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INFRAESTRUCTURA: GEOCERCAS + REGLAS
    // ─────────────────────────────────────────────────────────────────────────
    private function createInfrastructure($faker): array
    {
        // Geocercas en zonas reales de GDL
        $geofences = [];
        foreach ($this->zones as $zone) {
            $geofences[] = Geofence::create([
                'company_id'  => $this->companyId,
                'name'        => 'Zona ' . $zone['name'],
                'type'        => 'circle',
                'category'    => 'safe',
                'radius'      => rand(500, 2000),
                'coordinates' => json_encode(['lat' => $zone['lat'], 'lng' => $zone['lng']]),
            ]);
        }

        // Reglas de alerta variadas
        $rules = [];

        $rules['overspeed'] = AlertRule::create([
            'company_id' => $this->companyId,
            'name'       => 'Exceso de Velocidad',
            'type'       => 'overspeed',
            'value'      => 110,
            'priority'   => 'high',
            'is_active'  => true,
        ]);

        $rules['geofence_exit'] = AlertRule::create([
            'company_id'  => $this->companyId,
            'name'        => 'Salida de Zona Autorizada',
            'type'        => 'geofence_exit',
            'geofence_id' => $geofences[0]->id,
            'priority'    => 'medium',
            'is_active'   => true,
        ]);

        $rules['geofence_enter'] = AlertRule::create([
            'company_id'  => $this->companyId,
            'name'        => 'Entrada a Zona Restringida',
            'type'        => 'geofence_enter',
            'geofence_id' => $geofences[3]->id,
            'priority'    => 'medium',
            'is_active'   => true,
        ]);

        $rules['sos'] = AlertRule::create([
            'company_id' => $this->companyId,
            'name'       => 'Botón SOS',
            'type'       => 'sos_button',
            'priority'   => 'high',
            'is_active'  => true,
        ]);

        $rules['power_cut'] = AlertRule::create([
            'company_id' => $this->companyId,
            'name'       => 'Corte de Energía',
            'type'       => 'power_cut',
            'priority'   => 'high',
            'is_active'  => true,
        ]);

        $rules['harsh_braking'] = AlertRule::create([
            'company_id' => $this->companyId,
            'name'       => 'Frenado Brusco',
            'type'       => 'harsh_braking',
            'priority'   => 'medium',
            'is_active'  => true,
        ]);

        $rules['towing'] = AlertRule::create([
            'company_id' => $this->companyId,
            'name'       => 'Posible Arrastre',
            'type'       => 'towing',
            'priority'   => 'high',
            'is_active'  => true,
        ]);

        $rules['ignition_off'] = AlertRule::create([
            'company_id' => $this->companyId,
            'name'       => 'Motor Apagado',
            'type'       => 'ignition_off',
            'priority'   => 'low',
            'is_active'  => true,
        ]);

        return [$geofences, $rules];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLIENTES
    // ─────────────────────────────────────────────────────────────────────────
    private function createClients($faker, array $geofences, array $rules): void
    {
        /*
         * Perfiles de cliente para el demo:
         *   0–9  → Al corriente (active)          — buenos pagadores
         *  10–19 → Vencidos (past_due)             — morosos recientes
         *  20–24 → Proceso legal (legal_process)   — morosos graves
         *  25–29 → Finalizados (finished)          — contratos cerrados
         */
        for ($i = 0; $i < 30; $i++) {
            $profile = $this->getClientProfile($i);
            $zone    = $this->zones[$i % count($this->zones)];
            $vModel  = $this->vehicleModels[$i % count($this->vehicleModels)];
            $year    = rand(2020, 2024);

            // ── Infraestructura GPS ───────────────────────────────────────
            $sim = SimCard::create([
                'company_id'   => $this->companyId,
                'phone_number' => '33' . $faker->unique()->numerify('########'),
                'carrier'      => $faker->randomElement(['Telcel', 'AT&T', 'Movistar']),
                'status'       => 'active',
            ]);

            $vehicle = Vehicle::create([
                'company_id' => $this->companyId,
                'name'       => "{$vModel['brand']} {$vModel['model']} {$year}",
                'brand'      => $vModel['brand'],
                'model'      => $vModel['model'],
                'year'       => $year,
                'type'       => $vModel['type'],
                'plate'      => strtoupper($faker->bothify('???-####')),
                'status'     => 'active',
            ]);

            $device = Device::create([
                'company_id'      => $this->companyId,
                'vehicle_id'      => $vehicle->id,
                'sim_id'          => $sim->id,
                'imei'            => $faker->unique()->numerify('3582##############'),
                'activation_code' => strtoupper($faker->unique()->bothify('V2-####')),
                'status'          => 'active',
                'activated_at'    => now()->subMonths(rand(1, 18)),
            ]);
            $vehicle->update(['device_id' => $device->id]);

            // Asignar reglas de alerta al vehículo
            $vehicle->alertRules()->attach($rules['overspeed']->id);
            $vehicle->alertRules()->attach($rules['geofence_exit']->id);
            if ($profile['status'] !== 'active') {
                $vehicle->alertRules()->attach($rules['power_cut']->id);
                $vehicle->alertRules()->attach($rules['towing']->id);
            }

            // ── Cuenta ───────────────────────────────────────────────────
            $account = Account::create([
                'company_id' => $this->companyId,
                'name'       => $faker->name,
                'email'      => $faker->unique()->safeEmail,
                'password'   => bcrypt('password'),
                'role'       => 'customer',
                'status'     => 'active',
            ]);

            CustomerProfile::create([
                'account_id'       => $account->id,
                'rfc'              => strtoupper($faker->bothify('????######???')),
                'phone_primary'    => $sim->phone_number,
                'phone_secondary'  => '33' . $faker->numerify('########'),
                'address_home'     => $faker->address,
                'job_title'        => $faker->jobTitle,
                'company_name'     => $faker->company,
            ]);

            CustomerRiskScore::create([
                'account_id' => $account->id,
                'score'      => $profile['risk_score'],
                'points'     => $profile['risk_points'],
                'reason'     => $profile['risk_reason'],
            ]);

            // ── Contrato ──────────────────────────────────────────────────
            $monthlyAmount  = $faker->randomElement([4500, 5200, 5800, 6200, 6800]);
            $contractStart  = now()->subMonths(rand(3, 20));
            $contract = LeaseContract::create([
                'company_id'      => $this->companyId,
                'account_id'      => $account->id,
                'vehicle_id'      => $vehicle->id,
                'contract_number' => 'CTO-' . (202600 + $i),
                'monthly_amount'  => $monthlyAmount,
                'down_payment'    => rand(15000, 35000),
                'amount_financed' => rand(120000, 250000),
                'payment_day'     => $profile['payment_day'],
                'grace_days'      => rand(3, 7),
                'auto_immobilize' => true,
                'is_immobilized'  => in_array($profile['status'], ['past_due', 'legal_process']),
                'status'          => $profile['status'],
                'created_at'      => $contractStart,
            ]);

            // ── Asignación vehículo → cliente ─────────────────────────────
            // Activos/past_due/legal_process → asignación activa. Finished → cerrada.
            $isActiveAssignment = $profile['status'] !== 'finished';
            VehicleAssignment::create([
                'vehicle_id'    => $vehicle->id,
                'account_id'    => $account->id,
                'assigned_from' => $contractStart->toDateString(),
                'assigned_to'   => $isActiveAssignment ? null : $contractStart->copy()->addMonths($profile['months_paid'])->toDateString(),
                'active'        => $isActiveAssignment,
            ]);

            // ── Historial de pagos ────────────────────────────────────────
            $this->createPaymentHistory($contract, $profile, $monthlyAmount, $contractStart, $faker);

            // ── Posición GPS ──────────────────────────────────────────────
            Position::create([
                'vehicle_id' => $vehicle->id,
                'device_id'  => $device->id,
                'latitude'   => $zone['lat']  + (rand(-50, 50) / 1000),
                'longitude'  => $zone['lng'] + (rand(-50, 50) / 1000),
                'speed'      => $profile['status'] === 'active' ? rand(0, 80) : 0,
                'heading'    => rand(0, 359),
                'timestamp'  => now()->subMinutes(rand(1, 120)),
            ]);

            // ── Multas ────────────────────────────────────────────────────
            $this->createFines($vehicle, $profile, $zone, $faker);

            // ── Alertas ───────────────────────────────────────────────────
            $this->createAlertLogs($vehicle, $account, $contract, $profile, $rules, $geofences, $zone, $faker);

            $this->command->line("  ✓ Cliente {$account->name} — {$profile['status']}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERFIL POR ÍNDICE
    // ─────────────────────────────────────────────────────────────────────────
    private function getClientProfile(int $i): array
    {
        // 0–9: activos y al corriente
        if ($i < 10) {
            return [
                'status'      => 'active',
                'payment_day' => rand(1, 28),
                'risk_score'  => 'Bajo',
                'risk_points' => rand(750, 980),
                'risk_reason' => 'Pagos puntuales y sin incidencias',
                'months_paid' => rand(3, 18),
                'fines_count' => 0,
            ];
        }

        // 10–19: vencidos (past_due)
        if ($i < 20) {
            return [
                'status'      => 'past_due',
                'payment_day' => now()->day - rand(2, 5), // día pasado
                'risk_score'  => 'Medio',
                'risk_points' => rand(350, 550),
                'risk_reason' => 'Retraso en pago del mes actual',
                'months_paid' => rand(1, 6),
                'fines_count' => rand(1, 2),
            ];
        }

        // 20–24: proceso legal
        if ($i < 25) {
            return [
                'status'      => 'legal_process',
                'payment_day' => 5,
                'risk_score'  => 'Alto',
                'risk_points' => rand(50, 250),
                'risk_reason' => 'Más de 30 días sin pago, unidad bloqueada',
                'months_paid' => rand(0, 2),
                'fines_count' => rand(2, 4),
            ];
        }

        // 25–29: finalizados
        return [
            'status'      => 'finished',
            'payment_day' => rand(1, 28),
            'risk_score'  => 'Bajo',
            'risk_points' => rand(600, 850),
            'risk_reason' => 'Contrato liquidado exitosamente',
            'months_paid' => rand(12, 24),
            'fines_count' => 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HISTORIAL DE PAGOS
    // ─────────────────────────────────────────────────────────────────────────
    private function createPaymentHistory(
        LeaseContract $contract,
        array $profile,
        float $monthlyAmount,
        Carbon $contractStart,
        $faker
    ): void {
        $monthsPaid = $profile['months_paid'];
        if ($monthsPaid <= 0) return;

        for ($m = 0; $m < $monthsPaid; $m++) {
            $paymentDate = $contractStart->copy()->addMonths($m)->day($contract->payment_day);

            // Algunos pagos llegan tarde (2-5 días después) para clientes morosos
            if ($profile['status'] !== 'active' && rand(0, 1)) {
                $paymentDate->addDays(rand(2, 8));
            }

            LeasePayment::create([
                'lease_contract_id' => $contract->id,
                'amount'            => $monthlyAmount,
                'payment_date'      => $paymentDate,
                'reference'         => strtoupper($faker->bothify('TRF-####-??')),
                'month_paid'        => $contractStart->copy()->addMonths($m)->format('Y-m'),
                'evidence_path'     => null,
                'created_by'        => 2, // admin de la empresa
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MULTAS
    // ─────────────────────────────────────────────────────────────────────────
    private function createFines(Vehicle $vehicle, array $profile, array $zone, $faker): void
    {
        $fineTypes = [
            ['desc' => 'Fotoinfracción — Exceso de velocidad',        'amount' => 1500],
            ['desc' => 'No respetar semáforo en rojo',                'amount' => 2000],
            ['desc' => 'Estacionamiento en zona prohibida',           'amount' => 800],
            ['desc' => 'Circulación en carril exclusivo',             'amount' => 1200],
            ['desc' => 'No portar tarjeta de circulación',            'amount' => 600],
            ['desc' => 'Exceso de velocidad en zona escolar',         'amount' => 2500],
            ['desc' => 'No respetar señal de alto',                   'amount' => 1800],
            ['desc' => 'Conducir hablando por celular',               'amount' => 1000],
        ];

        $count = $profile['fines_count'];
        for ($f = 0; $f < $count; $f++) {
            $type = $fineTypes[$f % count($fineTypes)];
            Fine::create([
                'company_id'  => $this->companyId,
                'vehicle_id'  => $vehicle->id,
                'source'      => $faker->randomElement(['Jalisco', 'SEMOVI GDL', 'Municipio Zapopan']),
                'reference'   => 'F-' . $faker->unique()->numberBetween(10000, 99999),
                'description' => $type['desc'],
                'amount'      => $type['amount'],
                'status'      => 'pending',
                'detected_at' => now()->subDays(rand(1, 45)),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ALERTAS
    // ─────────────────────────────────────────────────────────────────────────
    private function createAlertLogs(
        Vehicle $vehicle,
        Account $account,
        LeaseContract $contract,
        array $profile,
        array $rules,
        array $geofences,
        array $zone,
        $faker
    ): void {
        $alertsToCreate = [];
        $now = now();

        // ── Alertas según el perfil del cliente ───────────────────────────

        if ($profile['status'] === 'active') {
            // Clientes activos: pocas alertas, principalmente velocidad y geocerca
            $alertsToCreate[] = [
                'rule'    => $rules['overspeed'],
                'type'    => 'overspeed',
                'message' => 'Velocidad excesiva: ' . rand(115, 140) . ' km/h',
                'speed'   => rand(115, 140),
                'daysAgo' => rand(5, 30),
                'is_read' => true,
            ];

            if (rand(0, 1)) {
                $alertsToCreate[] = [
                    'rule'    => $rules['geofence_exit'],
                    'type'    => 'geofence_exit',
                    'message' => 'Vehículo salió de Zona ' . $zone['name'],
                    'speed'   => rand(30, 60),
                    'daysAgo' => rand(1, 15),
                    'is_read' => rand(0, 1) === 1,
                ];
            }

            if (rand(0, 3) === 0) {
                $alertsToCreate[] = [
                    'rule'    => $rules['harsh_braking'],
                    'type'    => 'harsh_braking',
                    'message' => 'Frenado brusco detectado',
                    'speed'   => rand(60, 100),
                    'daysAgo' => rand(1, 10),
                    'is_read' => rand(0, 1) === 1,
                ];
            }
        }

        if ($profile['status'] === 'past_due') {
            // Vencidos: velocidad alta, geocerca, y posible intento de evasión
            for ($a = 0; $a < rand(3, 6); $a++) {
                $alertsToCreate[] = [
                    'rule'    => $rules['overspeed'],
                    'type'    => 'overspeed',
                    'message' => 'Velocidad excesiva: ' . rand(120, 160) . ' km/h',
                    'speed'   => rand(120, 160),
                    'daysAgo' => rand(1, 20),
                    'is_read' => rand(0, 2) > 0,
                ];
            }

            $alertsToCreate[] = [
                'rule'    => $rules['geofence_exit'],
                'type'    => 'geofence_exit',
                'message' => 'Vehículo salió de zona autorizada sin aviso',
                'speed'   => rand(40, 80),
                'daysAgo' => rand(1, 7),
                'is_read' => false,
            ];

            if (rand(0, 1)) {
                $alertsToCreate[] = [
                    'rule'    => $rules['power_cut'],
                    'type'    => 'power_cut',
                    'message' => 'Corte de energía detectado — posible manipulación del GPS',
                    'speed'   => 0,
                    'daysAgo' => rand(1, 5),
                    'is_read' => false,
                ];
            }
        }

        if ($profile['status'] === 'legal_process') {
            // Proceso legal: alertas críticas — SOS, arrastre, corte de energía
            $alertsToCreate[] = [
                'rule'    => $rules['towing'],
                'type'    => 'towing',
                'message' => 'Posible arrastre o remolque detectado — revisar unidad',
                'speed'   => rand(5, 25),
                'daysAgo' => rand(1, 10),
                'is_read' => false,
            ];

            $alertsToCreate[] = [
                'rule'    => $rules['power_cut'],
                'type'    => 'power_cut',
                'message' => 'Corte de energía — GPS desconectado por el cliente',
                'speed'   => 0,
                'daysAgo' => rand(1, 8),
                'is_read' => false,
            ];

            if (rand(0, 1)) {
                $alertsToCreate[] = [
                    'rule'    => $rules['sos'],
                    'type'    => 'sos_button',
                    'message' => 'Botón SOS activado',
                    'speed'   => 0,
                    'daysAgo' => rand(1, 15),
                    'is_read' => false,
                ];
            }

            for ($a = 0; $a < rand(4, 8); $a++) {
                $alertsToCreate[] = [
                    'rule'    => $rules['overspeed'],
                    'type'    => 'overspeed',
                    'message' => 'Exceso de velocidad: ' . rand(130, 180) . ' km/h',
                    'speed'   => rand(130, 180),
                    'daysAgo' => rand(1, 30),
                    'is_read' => rand(0, 3) > 0,
                ];
            }

            // Múltiples salidas de geocerca
            for ($a = 0; $a < rand(2, 4); $a++) {
                $alertsToCreate[] = [
                    'rule'    => $rules['geofence_exit'],
                    'type'    => 'geofence_exit',
                    'message' => 'Salida de zona autorizada — vehículo en zona de riesgo',
                    'speed'   => rand(50, 90),
                    'daysAgo' => rand(1, 20),
                    'is_read' => false,
                ];
            }
        }

        if ($profile['status'] === 'finished') {
            // Finalizados: solo historial antiguo sin leer
            $alertsToCreate[] = [
                'rule'    => $rules['ignition_off'],
                'type'    => 'ignition_off',
                'message' => 'Motor apagado — último registro del contrato',
                'speed'   => 0,
                'daysAgo' => rand(30, 90),
                'is_read' => true,
            ];
        }

        // ── Insertar alertas ──────────────────────────────────────────────
        foreach ($alertsToCreate as $alert) {
            AlertLog::create([
                'company_id'    => $this->companyId,
                'vehicle_id'    => $vehicle->id,
                'alert_rule_id' => $alert['rule']->id,
                'type'          => $alert['type'],
                'message'       => $alert['message'],
                'latitude'      => $zone['lat']  + (rand(-20, 20) / 1000),
                'longitude'     => $zone['lng'] + (rand(-20, 20) / 1000),
                'speed'         => $alert['speed'],
                'is_read'       => $alert['is_read'],
                'occurred_at'   => $now->copy()->subDays($alert['daysAgo'])
                                        ->subHours(rand(0, 23))
                                        ->subMinutes(rand(0, 59)),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VEHÍCULOS LIBRES (sin arrendatario asignado)
    // ─────────────────────────────────────────────────────────────────────────
    private function createFreeVehicles($faker, array $rules): void
    {
        $freeModels = [
            ['brand' => 'Nissan',    'model' => 'March',  'year' => 2023],
            ['brand' => 'Chevrolet', 'model' => 'Spark',  'year' => 2022],
            ['brand' => 'Kia',       'model' => 'Picanto','year' => 2024],
            ['brand' => 'Toyota',    'model' => 'Corolla','year' => 2023],
            ['brand' => 'Volkswagen','model' => 'Polo',   'year' => 2024],
        ];

        foreach ($freeModels as $m) {
            $sim = SimCard::create([
                'company_id'   => $this->companyId,
                'phone_number' => '33' . $faker->unique()->numerify('########'),
                'carrier'      => 'Telcel',
                'status'       => 'active',
            ]);

            $vehicle = Vehicle::create([
                'company_id' => $this->companyId,
                'name'       => "{$m['brand']} {$m['model']} {$m['year']}",
                'brand'      => $m['brand'],
                'model'      => $m['model'],
                'year'       => $m['year'],
                'type'       => 'car',
                'plate'      => strtoupper($faker->bothify('???-####')),
                'status'     => 'active',
            ]);

            $device = Device::create([
                'company_id'      => $this->companyId,
                'vehicle_id'      => $vehicle->id,
                'sim_id'          => $sim->id,
                'imei'            => $faker->unique()->numerify('3582##############'),
                'activation_code' => strtoupper($faker->unique()->bothify('V2-####')),
                'status'          => 'active',
                'activated_at'    => now(),
            ]);
            $vehicle->update(['device_id' => $device->id]);
            $vehicle->alertRules()->attach($rules['overspeed']->id);

            // Posición GPS
            $zone = $this->zones[array_rand($this->zones)];
            Position::create([
                'vehicle_id' => $vehicle->id,
                'device_id'  => $device->id,
                'latitude'   => $zone['lat'] + (rand(-30, 30) / 1000),
                'longitude'  => $zone['lng'] + (rand(-30, 30) / 1000),
                'speed'      => 0,
                'heading'    => 0,
                'timestamp'  => now(),
            ]);

            $this->command->line("  ✓ Vehículo libre: {$vehicle->name} ({$vehicle->plate})");
        }
    }
}