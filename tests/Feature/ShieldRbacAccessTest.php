<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\DwhBranchMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class ShieldRbacAccessTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDwhConnection();
        $this->createRawSavingsTable();
    }

    public function test_staff_dashboard_hides_dwh_widgets_and_staff_cannot_access_guarded_pages(): void
    {
        [$user] = $this->createScopedUser('staff', 'TABUNGAN');

        $this->actingAs($user)
            ->get('/arsip_digital')
            ->assertOk()
            ->assertSee('Ringkasan Arsip')
            ->assertSee('Trend Upload Arsip')
            ->assertSee('Arsip Terbaru')
            ->assertDontSee('Ringkasan Target & Realisasi')
            ->assertDontSee('Realisasi Terendah');

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('rekap.print'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/users')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/branch-offices')
            ->assertForbidden();
    }

    public function test_kearsipan_can_access_recap_legacy_linker_and_archive_edit(): void
    {
        [$user, $branch, $category] = $this->createScopedUser('kearsipan', 'TABUNGAN');
        $archive = $this->createArchive($user, $branch, $category, 'Archive Kearsipan');

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rekap.print'))
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-inactive-archives')
            ->assertOk();

        $this->actingAs($user)
            ->get("/arsip_digital/archives/{$archive->id}/edit")
            ->assertOk();
    }

    public function test_it_can_access_branch_offices_and_coverage_but_not_legacy_linker(): void
    {
        [$user, $branch] = $this->createScopedUser('it', 'TABUNGAN');

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branch->id,
            'siardi_branch_code' => $branch->branch_code,
            'dwh_location_code' => '001',
            'dwh_location_name' => $branch->branch_name,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital/branch-offices')
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/dwh-coverage-dashboard')
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-inactive-archives')
            ->assertForbidden();
    }

    public function test_it_admin_can_edit_archives_and_open_legacy_linker_but_cannot_access_recap_or_coverage(): void
    {
        [$user, $branch, $category] = $this->createScopedUser('it_admin', 'TABUNGAN');
        $archive = $this->createArchive($user, $branch, $category, 'Archive IT Admin');

        $this->actingAs($user)
            ->get("/arsip_digital/archives/{$archive->id}/edit")
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-inactive-archives')
            ->assertOk();

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/dwh-coverage-dashboard')
            ->assertForbidden();
    }

    public function test_nonaktif_is_blocked_from_guarded_resources_and_pages(): void
    {
        [$user] = $this->createScopedUser('nonaktif', 'TABUNGAN');

        $this->actingAs($user)
            ->get('/arsip_digital/archives')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/dwh-coverage-dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-inactive-archives')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: BranchOffice, 2: Category}
     */
    private function createScopedUser(string $role, string $categoryName): array
    {
        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $category = Category::query()->create([
            'category_name' => $categoryName,
            'category_description' => $categoryName,
        ]);

        $user = User::query()->create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $role.'@example.com',
            'username' => $role,
            'password' => Hash::make('password'),
            'branch_office_id' => $branch->id,
        ]);

        $this->assignRole($user, $role);
        $user->permittedCategories()->attach($category->id);

        return [$user, $branch, $category];
    }

    private function createArchive(User $user, BranchOffice $branch, Category $category, string $name): Archive
    {
        return Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => $name,
            'archive_code' => strtoupper(str()->slug($name)),
            'archive_description' => 'Archive',
            'archive_path' => 'archives/'.str()->slug($name).'.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-13',
        ]);
    }
}
