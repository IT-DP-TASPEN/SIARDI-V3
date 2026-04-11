<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['name' => 'staff', 'guard_name' => 'web'],
            ['name' => 'it', 'guard_name' => 'web'],
            ['name' => 'user', 'guard_name' => 'web'],
        ];

        DB::table('roles')->upsert($roles, ['name', 'guard_name'], []);
    }
}
