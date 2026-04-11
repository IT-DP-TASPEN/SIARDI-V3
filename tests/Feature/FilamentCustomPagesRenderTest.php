<?php

namespace Tests\Feature;

use App\Models\BranchOffice;
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
