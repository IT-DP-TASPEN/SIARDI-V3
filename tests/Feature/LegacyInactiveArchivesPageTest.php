<?php

namespace Tests\Feature;

use App\Filament\Pages\LegacyArchiveLinker;
use App\Filament\Pages\LegacyInactiveArchives;
use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\LegacyArchiveInactive;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LegacyInactiveArchivesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_archive_returns_it_to_legacy_queue(): void
    {
        [$user, $archive] = $this->createInactiveArchiveContext();

        Livewire::actingAs($user)
            ->test(LegacyInactiveArchives::class)
            ->call('selectArchive', $archive->id)
            ->call('restoreArchive')
            ->assertDispatched('close-modal', id: LegacyInactiveArchives::DETAIL_MODAL_ID)
            ->assertSet('selectedArchiveId', null);

        $this->assertDatabaseMissing('legacy_archive_inactives', [
            'archive_id' => $archive->id,
        ]);

        $this->actingAs($user)
            ->get(LegacyInactiveArchives::getUrl())
            ->assertOk()
            ->assertDontSee('Inactive Legacy Archive');

        $this->actingAs($user)
            ->get(LegacyArchiveLinker::getUrl())
            ->assertOk()
            ->assertSee('Inactive Legacy Archive');
    }

    public function test_inactive_modal_shows_text_preview(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('archives/inactive-preview.txt', 'Isi preview inactive');

        [$user] = $this->createScopedUser();

        $archive = Archive::query()->create([
            'archive_category' => $user->permittedCategories()->firstOrFail()->id,
            'archive_user' => $user->id,
            'archive_name' => 'Inactive Preview Archive',
            'archive_code' => 'INA-TXT-001',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/inactive-preview.txt',
            'archive_type' => 'txt',
            'archive_branch_office' => $user->branch_office_id,
            'archive_date' => '2026-04-12',
        ]);

        LegacyArchiveInactive::query()->create([
            'archive_id' => $archive->id,
            'marked_inactive_by' => $user->id,
            'marked_inactive_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(LegacyInactiveArchives::class)
            ->call('selectArchive', $archive->id)
            ->assertSee('Preview Arsip')
            ->assertSee('Isi preview inactive')
            ->assertSee('items-start', false)
            ->assertSee('gap-y-8', false)
            ->assertSee('/storage/archives/inactive-preview.txt', false);
    }

    public function test_inactive_modal_shows_pdfjs_preview_shell_for_pdf_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('archives/inactive-preview.pdf', '%PDF-1.4 inactive');

        [$user] = $this->createScopedUser();

        $archive = Archive::query()->create([
            'archive_category' => $user->permittedCategories()->firstOrFail()->id,
            'archive_user' => $user->id,
            'archive_name' => 'Inactive PDF Preview Archive',
            'archive_code' => 'INA-PDF-001',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/inactive-preview.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $user->branch_office_id,
            'archive_date' => '2026-04-12',
        ]);

        LegacyArchiveInactive::query()->create([
            'archive_id' => $archive->id,
            'marked_inactive_by' => $user->id,
            'marked_inactive_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(LegacyInactiveArchives::class)
            ->call('selectArchive', $archive->id)
            ->assertSee('Preview Arsip')
            ->assertSee('data-siardi-pdf-preview', false)
            ->assertSee('data-role="canvas"', false)
            ->assertSee('/storage/archives/inactive-preview.pdf', false)
            ->assertSee('Fit Width');
    }

    public function test_inactive_page_uses_filament_pagination_for_multiple_results(): void
    {
        [$user, $category, $branch] = $this->createScopedUser();

        foreach (range(1, 51) as $index) {
            $archive = Archive::query()->create([
                'archive_category' => $category->id,
                'archive_user' => $user->id,
                'archive_name' => sprintf('Inactive Queue %02d', $index),
                'archive_code' => sprintf('INA-Q-%02d', $index),
                'archive_description' => 'Archive',
                'archive_path' => sprintf('archives/inactive-queue-%02d.pdf', $index),
                'archive_type' => 'pdf',
                'archive_branch_office' => $branch->id,
                'archive_date' => '2026-04-12',
            ]);

            $timestamp = Carbon::parse('2026-04-12 08:00:00')->addMinutes($index);
            $archive->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->saveQuietly();

            LegacyArchiveInactive::query()->create([
                'archive_id' => $archive->id,
                'marked_inactive_by' => $user->id,
                'marked_inactive_at' => $timestamp,
            ]);
        }

        $this->actingAs($user)
            ->get(LegacyInactiveArchives::getUrl())
            ->assertOk()
            ->assertSee('fi-pagination', false)
            ->assertSee('Inactive Queue 51')
            ->assertDontSee('Inactive Queue 01');

        $this->actingAs($user)
            ->get(LegacyInactiveArchives::getUrl().'?page=2')
            ->assertOk()
            ->assertSee('fi-pagination', false)
            ->assertSee('Inactive Queue 01')
            ->assertDontSee('Inactive Queue 02');
    }

    /**
     * @return array{0: User, 1: Archive}
     */
    private function createInactiveArchiveContext(): array
    {
        [$user] = $this->createScopedUser();

        $archive = Archive::query()->create([
            'archive_category' => $user->permittedCategories()->firstOrFail()->id,
            'archive_user' => $user->id,
            'archive_name' => 'Inactive Legacy Archive',
            'archive_code' => 'INA-LEG-001',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/inactive-legacy.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $user->branch_office_id,
            'archive_date' => '2026-04-12',
        ]);

        LegacyArchiveInactive::query()->create([
            'archive_id' => $archive->id,
            'marked_inactive_by' => $user->id,
            'marked_inactive_at' => now(),
        ]);

        return [$user, $archive];
    }

    /**
     * @return array{0: User, 1: Category, 2: BranchOffice}
     */
    private function createScopedUser(): array
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);
        $this->assignRole($user, 'kearsipan');
        $user->permittedCategories()->attach($category->id);

        return [$user, $category, $branch];
    }
}
