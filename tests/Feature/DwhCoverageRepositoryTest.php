<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Models\User;
use App\Repositories\DwhCoverageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class DwhCoverageRepositoryTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDwhConnection();
        $this->createRawSavingsTable();
    }

    public function test_reconciliation_counts_distinct_covered_records_and_excludes_closed_or_internal_rows(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
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
            ['_row_key' => 'A', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100000001', 'norekening' => '001000000000001', 'status_dokumen' => 'Active'],
            ['_row_key' => 'B', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100000002', 'norekening' => '001000000000002', 'status_dokumen' => 'Active'],
            ['_row_key' => 'C', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100000003', 'norekening' => '001000000000003', 'status_dokumen' => 'Closed'],
            ['_row_key' => 'D', 'as_of_date' => '2026-04-10', 'locationid' => '000', 'nocif' => '00100000004', 'norekening' => '000000OPER', 'status_dokumen' => 'Active'],
        ]);

        $archiveOne = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive One',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/archive-one.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        $archiveTwo = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive Two',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/archive-two.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        ArchiveBusinessReference::query()->create([
            'archive_id' => $archiveOne->id,
            'category_reference_field_id' => $primaryField->id,
            'reference_type' => 'savings_account_no',
            'raw_value' => '001000000000001',
            'normalized_value' => '001000000000001',
        ]);

        ArchiveBusinessReference::query()->create([
            'archive_id' => $archiveTwo->id,
            'category_reference_field_id' => $primaryField->id,
            'reference_type' => 'savings_account_no',
            'raw_value' => '001 000000000001',
            'normalized_value' => '001000000000001',
        ]);

        $repository = app(DwhCoverageRepository::class);
        $summary = $repository->getCoverageSummary(branchOfficeId: $branch->id, categoryId: $category->id)->first();
        $missing = $repository->getMissingRecords($category->id, $branch->id);

        $this->assertSame(2, $summary['target_count']);
        $this->assertSame(1, $summary['realized_count']);
        $this->assertSame(1, $summary['missing_count']);
        $this->assertSame('001000000000002', $missing->first()['business_key']);
        $this->assertCount(1, $missing);
    }

    public function test_search_reference_candidates_returns_exact_matches_with_reference_payload(): void
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

        DB::connection('dwh')->table('raw_savings')->insert([
            ['_row_key' => 'ROW-1', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100013785', 'norekening' => '001000000000001', 'status_dokumen' => 'Active'],
            ['_row_key' => 'ROW-2', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100099999', 'norekening' => '001000000000999', 'status_dokumen' => 'Active'],
        ]);

        $candidates = app(DwhCoverageRepository::class)->searchReferenceCandidates(
            $category,
            '001',
            [
                'cif' => '00100013785',
                'savings_account_no' => '001 000000000001',
            ],
        );

        $this->assertCount(1, $candidates);
        $this->assertSame('ROW-1', $candidates->first()['source_key']);
        $this->assertSame('00100013785', $candidates->first()['reference_values']['cif']);
        $this->assertSame('001000000000001', $candidates->first()['reference_values']['savings_account_no']);
    }
}
