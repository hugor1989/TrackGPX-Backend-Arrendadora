<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Company, Vehicle, Device, SimCard, Account, CustomerProfile, LeaseContract, VehicleAssignment, Position, Fine, RiskScore, AlertRule, AlertLog, CustomerRiskScore, Geofence};
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class DemoTrackGPXSeeder extends Seeder
{
    public function run()
    {
        $companyId = 2; 
        $faker = Faker::create('es_MX');

        DB::beginTransaction();
        try {
            // 1. LIMPIEZA QUIRÚRGICA
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $customerIds = Account::where('company_id', $companyId)->where('role', 'customer')->pluck('id');
            $vIds = Vehicle::where('company_id', $companyId)->pluck('id');
            
            AlertLog::where('company_id', $companyId)->delete();
            DB::table('alert_rule_vehicle')->whereIn('vehicle_id', $vIds)->delete();
            AlertRule::where('company_id', $companyId)->delete();
            Geofence::where('company_id', $companyId)->delete();
            LeaseContract::whereIn('account_id', $customerIds)->delete();
            VehicleAssignment::whereIn('account_id', $customerIds)->delete();
            CustomerProfile::whereIn('account_id', $customerIds)->delete();
            CustomerRiskScore::whereIn('account_id', $customerIds)->delete();
            Position::whereIn('vehicle_id', $vIds)->delete();
            Fine::whereIn('vehicle_id', $vIds)->delete();
            Device::where('company_id', $companyId)->delete();
            SimCard::where('company_id', $companyId)->delete();
            Vehicle::where('company_id', $companyId)->delete();
            Account::whereIn('id', $customerIds)->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // 2. REGLAS Y GEOCERCA
            $geofence = Geofence::create([
                'company_id' => $companyId,
                'name' => 'Zona GDL Centro',
                'type' => 'circle',
                'category' => 'safe',
                'radius' => 1000,
                'coordinates' => json_encode(['lat' => 20.6744, 'lng' => -103.3873])
            ]);

            $ruleSpeed = AlertRule::create([
                'company_id' => $companyId,
                'name' => 'Exceso Velocidad',
                'type' => 'overspeed',
                'value' => 110,
                'priority' => 'high'
            ]);

            // 3. GENERACIÓN DE 20 CLIENTES
            for ($i = 0; $i < 20; $i++) {
                $isLate = ($i % 3 == 0); 

                // INFRAESTRUCTURA
                $sim = SimCard::create([
                    'company_id'   => $companyId,
                    'phone_number' => '33' . $faker->unique()->numerify('########'),
                    'carrier'      => 'Telcel', 'status' => 'active'
                ]);

                $vehicle = Vehicle::create([
                    'company_id' => $companyId,
                    'name'       => $faker->randomElement(['Nissan Versa', 'Chevrolet Aveo']) . ' ' . rand(2021, 2024),
                    'plate'      => strtoupper($faker->bothify('???-####')),
                    'status'     => 'active'
                ]);

                $device = Device::create([
                    'company_id' => $companyId, 'vehicle_id' => $vehicle->id, 'sim_id' => $sim->id,
                    'imei' => $faker->unique()->numerify('3582##############'),
                    'activation_code' => strtoupper($faker->unique()->bothify('V2-####')),
                    'status' => 'active', 'activated_at' => now()
                ]);
                $vehicle->update(['device_id' => $device->id]);
                $vehicle->alertRules()->attach($ruleSpeed->id);

                // CUENTA
                $user = Account::create([
                    'company_id' => $companyId,
                    'name'       => $faker->name,
                    'email'      => $faker->unique()->safeEmail,
                    'password'   => bcrypt('password'),
                    'role'       => 'customer',
                    'status'     => 'active'
                ]);

                // PERFIL (CORREGIDO con address_home)
                CustomerProfile::create([
                    'account_id'      => $user->id,
                    'rfc'             => strtoupper($faker->bothify('????######???')),
                    'phone_primary'   => $sim->phone_number,
                    'phone_secondary' => $faker->phoneNumber,
                    'address_home'    => $faker->address,
                    'job_title'       => $faker->jobTitle,
                    'company_name'    => $faker->company
                ]);

                // SCORE (CORREGIDO con points aquí)
                CustomerRiskScore::create([
                    'account_id' => $user->id,
                    'score'      => $isLate ? 'Alto' : 'Bajo',
                    'points'     => $isLate ? rand(100, 400) : rand(700, 950),
                    'reason'     => $isLate ? 'Retraso recurrente' : 'Pagos puntuales'
                ]);

                // CONTRATO
                LeaseContract::create([
                    'company_id' => $companyId, 'account_id' => $user->id, 'vehicle_id' => $vehicle->id,
                    'contract_number' => 'CTO-' . (202600 + $i),
                    'monthly_amount' => 5200, 'down_payment' => 25000, 'amount_financed' => 170000,
                    'payment_day' => $isLate ? now()->day : 15, 'status' => $isLate ? 'past_due' : 'active'
                ]);

                // MULTAS Y ALERTAS
                if ($isLate) {
                    Fine::create([
                        'company_id' => $companyId, 'vehicle_id' => $vehicle->id,
                        'source' => 'Jalisco', 'reference' => 'F-'.$faker->numberBetween(1000,9999),
                        'amount' => 1500, 'status' => 'pending', 'description' => 'Fotoinfracción',
                        'detected_at' => now()->subDays(3)
                    ]);

                    AlertLog::create([
                        'company_id' => $companyId, 'vehicle_id' => $vehicle->id, 'alert_rule_id' => $ruleSpeed->id,
                        'type' => 'overspeed', 'message' => "Velocidad excesiva: 120km/h",
                        'latitude' => 20.6744, 'longitude' => -103.3873, 'speed' => 120, 'occurred_at' => now()
                    ]);
                }

                Position::create([
                    'vehicle_id' => $vehicle->id, 'device_id' => $device->id,
                    'latitude' => 20.6744 + (rand(-30, 30) / 1000), 'longitude' => -103.3873 + (rand(-30, 30) / 1000),
                    'timestamp' => now()
                ]);
            }

            DB::commit();
            $this->command->info("✅ Seeder completado exitosamente para TrackGPX (ID: 2).");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Error: " . $e->getMessage());
        }
    }
}