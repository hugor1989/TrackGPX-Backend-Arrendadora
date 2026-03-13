<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountsRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Asume que ya existe una company con id=1
        $companyId = 1;

        $accounts = [
            [
                'role'  => 'super_admin',
                'name'  => 'Super Administrador',
                'email' => 'superadmin@arrendadora.com',
                'desc'  => 'Acceso total al sistema',
            ],
            [
                'role'  => 'company_admin',
                'name'  => 'Admin Empresa',
                'email' => 'admin@arrendadora.com',
                'desc'  => 'Administrador de la arrendadora',
            ],
            [
                'role'  => 'risk_analyst',
                'name'  => 'Ana Riesgo',
                'email' => 'riesgo@arrendadora.com',
                'desc'  => 'Analista de Riesgo Crediticio',
            ],
            [
                'role'  => 'collection_manager',
                'name'  => 'Carlos Cobranza',
                'email' => 'cobranza@arrendadora.com',
                'desc'  => 'Gestor de Cobranza y Recuperación',
            ],
            [
                'role'  => 'sales_executive',
                'name'  => 'Laura Ventas',
                'email' => 'ventas@arrendadora.com',
                'desc'  => 'Ejecutivo de Ventas',
            ],
            [
                'role'  => 'customer',
                'name'  => 'Cliente Demo',
                'email' => 'cliente@demo.com',
                'desc'  => 'Cliente con acceso a app móvil',
            ],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->updateOrInsert(
                ['email' => $account['email']],
                [
                    'company_id' => $companyId,
                    'name'       => $account['name'],
                    'email'      => $account['email'],
                    'password'   => Hash::make('password123'),
                    'role'       => $account['role'],
                    'status'     => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->info("✓ [{$account['role']}] {$account['name']} — {$account['desc']}");
        }
    }
}