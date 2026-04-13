<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Models\User;
use App\Services\DashboardSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class DashboardSnapshotServiceTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDwhConnection();
        $this->createRawSavingsTable();
    }

    public function test_archive_overview_respects_user_branch_and_category_scope(): void
    {
        $tabungan = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $audit = Category::query()->create([
            'category_name' => 'AUDIT',
            'category_description' => 'Audit',
        ]);

        $primaryField = CategoryReferenceField::query()->create([
            'category_id' => $tabungan->id,
            'reference_type' => 'savings_account_no',
            'label' => 'Nomor Rekening Tabungan',
            'input_type' => 'text',
            'sort_order' => 20,
            'is_required' => true,
            'is_primary_match_key' => true,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'savings',
        ]);

        $branchOne = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $branchTwo = BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branchOne->id,
        ]);
        $user->permittedCategories()->attach([$tabungan->id]);

        $visibleUnlinked = Archive::query()->create([
            'archive_category' => $tabungan->id,
            'archive_user' => $user->id,
            'archive_name' => 'Visible Unlinked',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/visible-unlinked.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-10',
        ]);

        $visibleLinked = Archive::query()->create([
            'archive_category' => $tabungan->id,
            'archive_user' => $user->id,
            'archive_name' => 'Visible Linked',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/visible-linked.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-10',
        ]);

        ArchiveBusinessReference::query()->create([
            'archive_id' => $visibleLinked->id,
            'category_reference_field_id' => $primaryField->id,
            'reference_type' => 'savings_account_no',
            'raw_value' => '001000000000001',
            'normalized_value' => '001000000000001',
        ]);

        Archive::query()->create([
            'archive_category' => $audit->id,
            'archive_user' => $user->id,
            'archive_name' => 'Wrong Category',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/wrong-category.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-10',
        ]);

        Archive::query()->create([
            'archive_category' => $tabungan->id,
            'archive_user' => $user->id,
            'archive_name' => 'Wrong Branch',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/wrong-branch.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchTwo->id,
            'archive_date' => '2026-04-10',
        ]);

        $overview = app(DashboardSnapshotService::class)->getArchiveOverview($user);

        $this->assertSame(2, $overview['total_archives']);
        $this->assertSame(2, $overview['uploads_this_month']);
        $this->assertSame(2, $overview['uploads_last_7_days']);
        $this->assertSame(1, $overview['unlinked_supported_archives']);
    }

    public function test_coverage_overview_and_hotspots_aggregate_visible_dwh_data(): void
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

        $branchOne = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $branchTwo = BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        $headOffice = BranchOffice::query()->create([
            'branch_code' => '00',
            'branch_name' => 'Kantor Pusat Manajemen',
        ]);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branchOne->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'Kantor Pusat Operasional',
            'is_active' => true,
        ]);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branchTwo->id,
            'siardi_branch_code' => '02',
            'dwh_location_code' => '002',
            'dwh_location_name' => 'KC Bogor',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'branch_office_id' => $headOffice->id,
        ]);
        $this->assignRole($admin, 'super_admin');

        DB::connection('dwh')->table('raw_savings')->insert([
            ['_row_key' => 'A1', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100000001', 'norekening' => '001000000000001', 'status_dokumen' => 'Active'],
            ['_row_key' => 'A2', 'as_of_date' => '2026-04-10', 'locationid' => '001', 'nocif' => '00100000002', 'norekening' => '001000000000002', 'status_dokumen' => 'Active'],
            ['_row_key' => 'B1', 'as_of_date' => '2026-04-10', 'locationid' => '002', 'nocif' => '00200000001', 'norekening' => '002000000000001', 'status_dokumen' => 'Active'],
            ['_row_key' => 'B2', 'as_of_date' => '2026-04-10', 'locationid' => '002', 'nocif' => '00200000002', 'norekening' => '002000000000002', 'status_dokumen' => 'Active'],
            ['_row_key' => 'B3', 'as_of_date' => '2026-04-10', 'locationid' => '002', 'nocif' => '00200000003', 'norekening' => '002000000000003', 'status_dokumen' => 'Active'],
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $admin->id,
            'archive_name' => 'Covered Archive',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/covered.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-10',
        ]);

        ArchiveBusinessReference::query()->create([
            'archive_id' => $archive->id,
            'category_reference_field_id' => $primaryField->id,
            'reference_type' => 'savings_account_no',
            'raw_value' => '001000000000001',
            'normalized_value' => '001000000000001',
        ]);

        $service = app(DashboardSnapshotService::class);
        $overview = $service->getCoverageOverview($admin);
        $hotspots = $service->getCoverageHotspots($admin);

        $this->assertTrue($overview['available']);
        $this->assertSame(5, $overview['target_total']);
        $this->assertSame(1, $overview['realized_total']);
        $this->assertSame(4, $overview['missing_total']);
        $this->assertSame(20.0, $overview['coverage_percentage']);
        $this->assertSame('02', $hotspots->first()['branch_code']);
        $this->assertSame(3, $hotspots->first()['missing_count']);
    }
}
