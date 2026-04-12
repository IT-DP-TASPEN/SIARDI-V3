<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BusinessReferenceConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategoryReferenceFieldSeeder::class,
            DwhBranchMappingSeeder::class,
        ]);
    }
}
