<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = [
            'super_admin',
            'company_admin',
            'risk_analyst',
            'collection_manager',
            'sales_executive',
            'customer'
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role],
                ['name' => $role],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('name', [
            'super_admin',
            'company_admin',
            'risk_analyst',
            'collection_manager',
            'sales_executive',
            'customer'
        ])->delete();
    }
};
