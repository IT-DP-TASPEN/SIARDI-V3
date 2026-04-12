<?php

namespace Tests\Feature;

use App\Models\BranchOffice;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessReferenceConfigurationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_targeted_business_reference_seeder_populates_supported_categories_and_branch_mappings(): void
    {
        foreach (['TABUNGAN', 'KREDIT', 'BILYET DEPOSITO'] as $categoryName) {
            Category::query()->create([
                'category_name' => $categoryName,
                'category_description' => $categoryName,
            ]);
        }

        BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        $this->seed(\Database\Seeders\BusinessReferenceConfigurationSeeder::class);

        $this->assertDatabaseCount('category_reference_fields', 6);
        $this->assertDatabaseCount('dwh_branch_mappings', 2);
        $this->assertDatabaseHas('category_reference_fields', [
            'reference_type' => 'savings_account_no',
            'label' => 'Nomor Rekening Tabungan',
        ]);
        $this->assertDatabaseHas('category_reference_fields', [
            'reference_type' => 'loan_account_no',
            'label' => 'Nomor Rekening Kredit',
        ]);
        $this->assertDatabaseHas('category_reference_fields', [
            'reference_type' => 'deposito_bilyet_no',
            'label' => 'Nomor Bilyet Deposito',
        ]);
        $this->assertDatabaseHas('dwh_branch_mappings', [
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
        ]);
        $this->assertDatabaseHas('dwh_branch_mappings', [
            'siardi_branch_code' => '02',
            'dwh_location_code' => '002',
        ]);
    }
}
