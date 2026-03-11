<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Users / Accounts
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'accounts.delete',

            // Vehicles
            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.delete',

            // Drivers
            'drivers.view',
            'drivers.create',
            'drivers.update',
            'drivers.delete',

            // Devices
            'devices.view',
            'devices.create',
            'devices.update',
            'devices.delete',

            // Geofences
            'geofences.view',
            'geofences.create',
            'geofences.update',
            'geofences.delete',

            // Trips
            'trips.view',

            // Alerts
            'alerts.view',
            'alerts.resolve',

            // Company Settings
            'company.settings.update',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm],
                ['name' => ucfirst(str_replace('.', ' ', $perm))]
            );
        }
    }
}
