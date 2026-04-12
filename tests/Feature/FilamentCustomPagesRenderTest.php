<?php

namespace Tests\Feature;

use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\DwhBranchMapping;
use App\Models\Role;
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
            ->assertSee('Ringkasan Coverage DWH');
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
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'Kantor Pusat Operasional',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital')
            ->assertOk()
            ->assertSee('Ringkasan Coverage DWH')
            ->assertSee('DWH unavailable');
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
            ->assertDontSee('Ringkasan Coverage DWH')
            ->assertDontSee('Coverage Terendah');
    }

    public function test_dwh_coverage_page_renders_with_empty_state(): void
    {
        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/arsip_digital/dwh-coverage-dashboard')
            ->assertOk()
            ->assertSee('Filter Rekonsiliasi')
            ->assertSee('Belum ada data rekonsiliasi.');
    }

    public function test_legacy_linking_page_renders_with_empty_state(): void
    {
        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertOk()
            ->assertSee('Filter Arsip Legacy')
            ->assertSee('Tidak ada arsip legacy yang cocok.');
    }

    private function createAdminUser(): User
    {
        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $role = Role::query()->create([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-render@example.com',
            'username' => 'admin-render',
            'password' => Hash::make('password'),
            'branch_office_id' => $branch->id,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
