<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Mapeo de roles viejos → nuevos ────────────────────────────────
        $roleMap = [
            'super_admin'   => 'super_admin',      // Sin cambio
            'company_admin' => 'company_admin',    // Sin cambio
            'fleet_manager' => 'risk_analyst',     // Analista de Riesgo
            'customer'      => 'customer',         // Sin cambio (app cliente)
            'driver'        => 'sales_executive',  // Ejecutivo de Ventas
        ];

        // ── 2. Migrar datos existentes ANTES de cambiar el enum ───────────────
        foreach ($roleMap as $old => $new) {
            if ($old !== $new) {
                DB::table('accounts')
                    ->where('role', $old)
                    ->update(['role' => $new]);
            }
        }

        // ── 3. Modificar el enum con los nuevos valores ───────────────────────
        // MySQL no permite ALTER directamente en enums con datos,
        // usamos DB::statement para forzarlo de forma segura.
        DB::statement("
            ALTER TABLE `accounts`
            MODIFY COLUMN `role` ENUM(
                'super_admin',
                'company_admin',
                'risk_analyst',
                'collection_manager',
                'sales_executive',
                'customer'
            ) COLLATE utf8mb4_unicode_ci NOT NULL
        ");
    }

    public function down(): void
    {
        // ── 1. Revertir datos al enum original ────────────────────────────────
        $roleMapReverse = [
            'risk_analyst'       => 'fleet_manager',
            'collection_manager' => 'fleet_manager', // No existía antes, fallback
            'sales_executive'    => 'driver',
        ];

        foreach ($roleMapReverse as $new => $old) {
            DB::table('accounts')
                ->where('role', $new)
                ->update(['role' => $old]);
        }

        // ── 2. Restaurar el enum original ─────────────────────────────────────
        DB::statement("
            ALTER TABLE `accounts`
            MODIFY COLUMN `role` ENUM(
                'super_admin',
                'company_admin',
                'fleet_manager',
                'customer',
                'driver'
            ) COLLATE utf8mb4_unicode_ci NOT NULL
        ");
    }
};