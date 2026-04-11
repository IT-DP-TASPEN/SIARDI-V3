<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Models\User;
use App\Repositories\DwhCoverageRepository;
use App\Services\ArchiveBusinessReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class LegacyArchiveLinkingTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDwhConnection();
        $this->createRawSavingsTable();
    }

    public function test_manual_linking_updates_realized_coverage(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $cifField = CategoryReferenceField::query()->create([
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

        $primaryField = CategoryReferenceField::query()->create([
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

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branch->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'Kantor Pusat Operasional',
            'is_active' => true,
        ]);

        DB::connection('dwh')->table('raw_savings')->insert([
            ['_row_key' => 'A', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100013785', 'norekening' => '001000000000001', 'status_dokumen' => 'Active'],
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Legacy Savings Archive',
            'archive_code' => 'LEGACY-001',
            'archive_description' => 'Legacy archive',
            'archive_path' => 'archives/legacy-savings.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        $repository = app(DwhCoverageRepository::class);
        $service = app(ArchiveBusinessReferenceService::class);

        $before = $repository->getCoverageSummary(branchOfficeId: $branch->id, categoryId: $category->id)->first();

        $service->syncForArchive($archive, [
            $cifField->id => '00100013785',
            $primaryField->id => '001 000000000001',
        ]);

        $after = $repository->getCoverageSummary(branchOfficeId: $branch->id, categoryId: $category->id)->first();

        $this->assertSame(0, $before['realized_count']);
        $this->assertSame(1, $after['realized_count']);
        $this->assertSame(0, $after['missing_count']);
    }
}
