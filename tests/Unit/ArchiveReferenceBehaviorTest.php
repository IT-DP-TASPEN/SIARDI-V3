<?php

namespace Tests\Unit;

use App\Filament\Resources\ArchiveResource;
use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\LegacyArchiveInactive;
use App\Models\User;
use App\Services\ArchiveBusinessReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class ArchiveReferenceBehaviorTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDwhConnection();
        $this->createRawSavingsTable();
    }

    public function test_dynamic_category_reference_schema_is_configuration_driven(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'cif',
            'label' => 'CIF',
            'input_type' => 'text',
            'sort_order' => 10,
            'is_required' => true,
            'is_primary_match_key' => false,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'savings',
        ]);

        CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'savings_account_no',
            'label' => 'Nomor Rekening Tabungan',
            'input_type' => 'text',
            'sort_order' => 20,
            'is_required' => true,
            'is_primary_match_key' => true,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'savings',
        ]);

        $schema = ArchiveResource::buildBusinessReferenceSchema($category->id);

        $this->assertCount(2, $schema);
        $this->assertSame('CIF', $schema[0]->getLabel());
        $this->assertSame('Nomor Rekening Tabungan', $schema[1]->getLabel());
    }

    public function test_kredit_schema_ignores_deprecated_loan_contract_reference(): void
    {
        $category = Category::query()->create([
            'category_name' => 'KREDIT',
            'category_description' => 'Kredit',
        ]);

        CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'cif',
            'label' => 'CIF',
            'input_type' => 'text',
            'sort_order' => 10,
            'is_required' => true,
            'is_primary_match_key' => false,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'loans',
        ]);

        CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'loan_account_no',
            'label' => 'Nomor Rekening Kredit',
            'input_type' => 'text',
            'sort_order' => 20,
            'is_required' => true,
            'is_primary_match_key' => true,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'loans',
        ]);

        CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'loan_contract_no',
            'label' => 'Nomor Perjanjian Kredit',
            'input_type' => 'text',
            'sort_order' => 30,
            'is_required' => false,
            'is_primary_match_key' => false,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'loans',
        ]);

        $schema = ArchiveResource::buildBusinessReferenceSchema($category->id);

        $this->assertCount(2, $schema);
        $this->assertSame('CIF', $schema[0]->getLabel());
        $this->assertSame('Nomor Rekening Kredit', $schema[1]->getLabel());
    }

    public function test_reference_sync_normalizes_values_and_records_dwh_match(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $field = CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'savings_account_no',
            'label' => 'Nomor Rekening Tabungan',
            'input_type' => 'text',
            'sort_order' => 20,
            'is_required' => true,
            'is_primary_match_key' => true,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'savings',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);

        DB::table('dwh_branch_mappings')->insert([
            'branch_office_id' => $branch->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'Kantor Pusat Operasional',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('dwh')->table('raw_savings')->insert([
            '_row_key' => 'ROW-1',
            'as_of_date' => '2026-04-10',
            'locationid' => '001',
            'nocif' => '00100013785',
            'norekening' => '001000000000001',
            'status_dokumen' => 'Active',
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/archive.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, [
            $field->id => '001 000000000001',
        ]);

        $reference = $archive->businessReferences()->firstOrFail();

        $this->assertSame('001000000000001', $reference->normalized_value);
        $this->assertSame('raw_savings', $reference->matched_table);
        $this->assertSame('ROW-1', $reference->matched_source_key);
    }

    public function test_linkage_status_reports_configuration_gap_for_supported_category_without_fields(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/archive.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        $status = app(ArchiveBusinessReferenceService::class)->getLinkageStatus($archive);

        $this->assertSame('Konfigurasi Belum Aktif', $status['label']);
        $this->assertSame('warning', $status['color']);
    }

    public function test_linkage_status_reports_inactive_when_archive_has_inactive_marker(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive Inactive',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/archive-inactive.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        LegacyArchiveInactive::query()->create([
            'archive_id' => $archive->id,
            'marked_inactive_by' => $user->id,
            'marked_inactive_at' => now(),
        ]);

        $status = app(ArchiveBusinessReferenceService::class)->getLinkageStatus($archive->fresh());

        $this->assertSame('Inactive', $status['label']);
        $this->assertSame('gray', $status['color']);
    }
}
