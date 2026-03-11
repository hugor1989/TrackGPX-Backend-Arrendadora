<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'superadmin', 'company_id' => null],
            ['name' => 'Admin', 'slug' => 'admin', 'company_id' => null],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'company_id' => null],
            ['name' => 'Driver', 'slug' => 'driver', 'company_id' => null],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
