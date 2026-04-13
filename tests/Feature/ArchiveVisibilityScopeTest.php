<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\DwhBranchMapping;
use App\Models\User;
use App\Services\ArchiveVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class ArchiveVisibilityScopeTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDwhConnection();
        $this->createRawSavingsTable();
        $this->createRawLoansTable();
    }

    public function test_non_head_office_kearsipan_archive_surfaces_are_scoped_to_own_branch_and_allowed_categories(): void
    {
        [$branchOne, $branchTwo, $tabungan, $audit] = $this->createArchiveScopeFixtures();

        $user = User::factory()->create([
            'branch_office_id' => $branchOne->id,
        ]);
        $this->assignRole($user, 'kearsipan');
        $user->permittedCategories()->attach($tabungan->id);

        Archive::query()->create([
            'archive_category' => $tabungan->id,
            'archive_user' => $user->id,
            'archive_name' => 'Visible Tabungan Branch One',
            'archive_description' => 'Visible',
            'archive_path' => 'archives/visible-tabungan-1.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-12',
        ]);

        Archive::query()->create([
            'archive_category' => $tabungan->id,
            'archive_user' => $user->id,
            'archive_name' => 'Hidden Tabungan Branch Two',
            'archive_description' => 'Hidden',
            'archive_path' => 'archives/hidden-tabungan-2.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchTwo->id,
            'archive_date' => '2026-04-12',
        ]);

        Archive::query()->create([
            'archive_category' => $audit->id,
            'archive_user' => $user->id,
            'archive_name' => 'Hidden Audit Branch One',
            'archive_description' => 'Hidden',
            'archive_path' => 'archives/hidden-audit-1.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-12',
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital/archives')
            ->assertOk()
            ->assertSee('Visible Tabungan Branch One')
            ->assertDontSee('Hidden Tabungan Branch Two')
            ->assertDontSee('Hidden Audit Branch One');

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertOk()
            ->assertSee('Visible Tabungan Branch One')
            ->assertDontSee('Hidden Tabungan Branch Two')
            ->assertDontSee('Hidden Audit Branch One');
    }

    public function test_non_head_office_super_admin_coverage_page_is_scoped_to_own_branch_and_allowed_categories(): void
    {
        $tabungan = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $kredit = Category::query()->create([
            'category_name' => 'KREDIT',
            'category_description' => 'Kredit',
        ]);

        $branchOne = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $branchTwo = BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branchOne->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'KC Karawang',
            'is_active' => true,
        ]);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branchTwo->id,
            'siardi_branch_code' => '02',
            'dwh_location_code' => '002',
            'dwh_location_name' => 'KC Bogor',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branchOne->id,
        ]);
        $this->assignRole($user, 'super_admin');
        $user->permittedCategories()->attach($tabungan->id);

        DB::connection('dwh')->table('raw_savings')->insert([
            ['_row_key' => 'A1', 'as_of_date' => '2026-04-12', 'locationid' => '001', 'nocif' => '00100000001', 'norekening' => '001000000000001', 'status_dokumen' => 'Active'],
            ['_row_key' => 'B1', 'as_of_date' => '2026-04-12', 'locationid' => '002', 'nocif' => '00200000001', 'norekening' => '002000000000001', 'status_dokumen' => 'Active'],
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital/dwh-coverage-dashboard')
            ->assertOk()
            ->assertSee('KC Karawang')
            ->assertDontSee('KC Bogor')
            ->assertDontSee('KREDIT')
            ->assertDontSee('DWH');
    }

    public function test_non_head_office_kearsipan_recap_page_and_print_follow_branch_scope(): void
    {
        $kredit = Category::query()->create([
            'category_name' => 'KREDIT',
            'category_description' => 'Kredit',
        ]);

        $tabungan = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $branchOne = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $branchTwo = BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branchOne->id,
        ]);
        $this->assignRole($user, 'kearsipan');
        $user->permittedCategories()->attach($kredit->id);

        foreach ([
            [$branchOne->id, $kredit->id, 'Visible Kredit'],
            [$branchTwo->id, $kredit->id, 'Hidden Branch Kredit'],
            [$branchOne->id, $tabungan->id, 'Hidden Category Tabungan'],
        ] as [$branchId, $categoryId, $name]) {
            Archive::query()->create([
                'archive_category' => $categoryId,
                'archive_user' => $user->id,
                'archive_name' => $name,
                'archive_description' => 'Archive',
                'archive_path' => 'archives/'.str()->slug($name).'.pdf',
                'archive_type' => 'pdf',
                'archive_branch_office' => $branchId,
                'archive_date' => '2026-04-12',
            ]);
        }

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertOk()
            ->assertSee('KC Karawang')
            ->assertDontSee('KC Bogor');

        $this->actingAs($user)
            ->get(route('rekap.print'))
            ->assertOk()
            ->assertSee('KC Karawang')
            ->assertDontSee('KC Bogor');
    }

    public function test_head_office_super_admin_keeps_global_archive_visibility(): void
    {
        [$branchOne, $branchTwo, $tabungan, $audit] = $this->createArchiveScopeFixtures();
        $headOffice = BranchOffice::query()->create([
            'branch_code' => '00',
            'branch_name' => 'Kantor Pusat Manajemen',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $headOffice->id,
        ]);
        $this->assignRole($user, 'super_admin');

        Archive::query()->create([
            'archive_category' => $tabungan->id,
            'archive_user' => $user->id,
            'archive_name' => 'Global Branch One Archive',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/global-one.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchOne->id,
            'archive_date' => '2026-04-12',
        ]);

        Archive::query()->create([
            'archive_category' => $audit->id,
            'archive_user' => $user->id,
            'archive_name' => 'Global Branch Two Archive',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/global-two.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchTwo->id,
            'archive_date' => '2026-04-12',
        ]);

        $visibleArchiveNames = app(ArchiveVisibilityService::class)
            ->applyArchiveScope(Archive::query()->orderBy('archive_name'), $user)
            ->pluck('archive_name')
            ->all();

        $this->assertSame([
            'Global Branch One Archive',
            'Global Branch Two Archive',
        ], $visibleArchiveNames);
    }

    /**
     * @return array{0: BranchOffice, 1: BranchOffice, 2: Category, 3: Category}
     */
    private function createArchiveScopeFixtures(): array
    {
        $branchOne = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $branchTwo = BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        $tabungan = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $audit = Category::query()->create([
            'category_name' => 'AUDIT',
            'category_description' => 'Audit',
        ]);

        return [$branchOne, $branchTwo, $tabungan, $audit];
    }
}
