<?php

namespace Tests\Feature;

use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\DwhBranchMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FilamentCustomPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_recap_page_renders_with_filament_layout(): void
    {
        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/arsip_digital/archive-recap')
            ->assertOk()
            ->assertSee('Archive Recap')
            ->assertSee('Laporan Kinerja Aplikasi SIARDI');
    }

    public function test_dashboard_page_renders_widgets_in_zero_data_state(): void
    {
        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/arsip_digital')
            ->assertOk()
            ->assertSee('SIARDI Dashboard')
            ->assertSee('Ringkasan Arsip')
            ->assertSee('Trend Upload Arsip')
            ->assertSee('Arsip Terbaru')
            ->assertSee('Ringkasan Target & Realisasi')
            ->assertDontSee('DWH');
    }

    public function test_dashboard_page_renders_degraded_dwh_state_without_crashing(): void
    {
        $user = $this->createAdminUser();

        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $user->branch_office_id,
            'siardi_branch_code' => '00',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'Kantor Pusat Operasional',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital')
            ->assertOk()
            ->assertSee('Ringkasan Target & Realisasi')
            ->assertSee('Data target unavailable')
            ->assertDontSee('DWH');
    }

    public function test_dashboard_hides_dwh_widgets_when_reconciliation_feature_is_disabled(): void
    {
        config()->set('siardi.features.dwh_reconciliation', false);

        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/arsip_digital')
            ->assertOk()
            ->assertSee('SIARDI Dashboard')
            ->assertSee('Ringkasan Arsip')
            ->assertDontSee('Ringkasan Target & Realisasi')
            ->assertDontSee('Realisasi Terendah');
    }

    public function test_dwh_coverage_page_renders_with_empty_state(): void
    {
        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/arsip_digital/dwh-coverage-dashboard')
            ->assertOk()
            ->assertSee('Filter Target & Realisasi')
            ->assertSee('Belum ada data target dan realisasi.')
            ->assertDontSee('DWH');
    }

    public function test_legacy_linking_page_renders_with_empty_state(): void
    {
        $user = $this->createAdminUser();
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);
        $user->permittedCategories()->attach($category->id);

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertOk()
            ->assertSee('Filter Arsip Legacy')
            ->assertSee('Tidak ada arsip legacy yang cocok.')
            ->assertDontSee('DWH')
            ->assertSee('legacy-linking-modal', false)
            ->assertDontSee('legacy-linking-workspace', false);
    }

    public function test_legacy_inactive_page_renders_with_empty_state(): void
    {
        $user = $this->createAdminUser();
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);
        $user->permittedCategories()->attach($category->id);

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-inactive-archives')
            ->assertOk()
            ->assertSee('Filter Arsip Inactive')
            ->assertSee('Belum ada arsip inactive.')
            ->assertDontSee('DWH');
    }

    private function createAdminUser(): User
    {
        $branch = BranchOffice::query()->create([
            'branch_code' => '00',
            'branch_name' => 'Kantor Pusat Manajemen',
        ]);

        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-render@example.com',
            'username' => 'admin-render',
            'password' => Hash::make('password'),
            'branch_office_id' => $branch->id,
        ]);

        $this->assignRole($user, 'super_admin');

        return $user;
    }
}
