<?php

namespace Database\Seeders;

use App\Models\BranchOffice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DwhBranchMappingSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            '01' => ['dwh_location_code' => '001', 'dwh_location_name' => 'Kantor Pusat Operasional', 'is_active' => true],
            '02' => ['dwh_location_code' => '002', 'dwh_location_name' => 'KC BOGOR', 'is_active' => true],
            '03' => ['dwh_location_code' => '003', 'dwh_location_name' => 'KC DEPOK', 'is_active' => true],
            '04' => ['dwh_location_code' => '004', 'dwh_location_name' => 'KC TANGERANG', 'is_active' => true],
            '05' => ['dwh_location_code' => '005', 'dwh_location_name' => 'KC JAKARTA TIMUR', 'is_active' => true],
            '06' => ['dwh_location_code' => '006', 'dwh_location_name' => 'KC KARAWANG', 'is_active' => true],
            '07' => ['dwh_location_code' => '007', 'dwh_location_name' => 'KC CIKARANG', 'is_active' => true],
            '08' => ['dwh_location_code' => '008', 'dwh_location_name' => 'KC PURWOKERTO', 'is_active' => true],
            '00' => ['dwh_location_code' => '000', 'dwh_location_name' => 'Internal', 'is_active' => false],
            '09' => ['dwh_location_code' => '009', 'dwh_location_name' => 'HC', 'is_active' => false],
        ];

        foreach ($definitions as $branchCode => $definition) {
            $branchOffice = BranchOffice::query()->where('branch_code', $branchCode)->first();

            if (! $branchOffice) {
                continue;
            }

            DB::table('dwh_branch_mappings')->updateOrInsert(
                ['branch_office_id' => $branchOffice->id],
                $definition + [
                    'branch_office_id' => $branchOffice->id,
                    'siardi_branch_code' => $branchCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
