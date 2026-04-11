<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $branchOffices = [
            ['branch_code' => '00', 'branch_name' => 'Kantor Pusat Manajemen'],
            ['branch_code' => '01', 'branch_name' => 'Kantor Pusat Operasional'],
            ['branch_code' => '02', 'branch_name' => 'KC Bogor'],
            ['branch_code' => '03', 'branch_name' => 'KC Depok'],
            ['branch_code' => '04', 'branch_name' => 'KC Tangerang'],
            ['branch_code' => '05', 'branch_name' => 'KC Jaktim'],
            ['branch_code' => '06', 'branch_name' => 'KC Karawang'],
            ['branch_code' => '07', 'branch_name' => 'KC Cikarang'],
            ['branch_code' => '08', 'branch_name' => 'KC Purwokerto'],
            ['branch_code' => '09', 'branch_name' => 'HC'],
        ];

        DB::table('branch_offices')->upsert($branchOffices, ['branch_code'], ['branch_name']);
    }
}
