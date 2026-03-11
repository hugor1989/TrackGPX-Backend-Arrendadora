<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $superadmin = Role::where('slug', 'superadmin')->first();
        $admin      = Role::where('slug', 'admin')->first();
        $supervisor = Role::where('slug', 'supervisor')->first();
        $driver     = Role::where('slug', 'driver')->first();

        $permissions = Permission::all();

        // SuperAdmin → TODOS
        $superadmin->permissions()->sync($permissions->pluck('id'));

        // Admin → todos excepto configuración global
        $admin->permissions()->sync(
            $permissions
                ->where('slug', '!=', 'company.settings.update')
                ->pluck('id')
        );

        // Supervisor → solo lectura
        $supervisor->permissions()->sync(
            $permissions->filter(fn($p) =>
                str_contains($p->slug, 'view')
            )->pluck('id')
        );

        // Driver → SOLO lo que usa la app del conductor
        $driver->permissions()->sync(
            $permissions->whereIn('slug', [
                'trips.view',
                'drivers.view'
            ])->pluck('id')
        );
    }
}
