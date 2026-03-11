<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        // Crear empresa del sistema
        $company = Company::firstOrCreate(
            ['slug' => 'system'],
            ['name' => 'TrackGPX System']
        );

        // Usuario
        $admin = Account::firstOrCreate(
            ['email' => 'admin@trackgpx.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('secret123'),
                'company_id' => $company->id,
                'status' => 1
            ]
        );

        // Asignar rol superadmin
        $role = Role::where('slug', 'superadmin')->first();
        $admin->roles()->sync([$role->id]);
    }
}
