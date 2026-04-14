<?php

namespace Tests\Feature;

use App\Filament\Resources\ArchiveResource\Pages\ListArchives;
use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\User;
use App\Support\ReferenceNormalizer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ArchiveCifTableSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('arsip_digital');
        Filament::bootCurrentPanel();
    }

    public function test_archive_list_shows_cif_column_state_for_archives_with_and_without_cif(): void
    {
        [$user, $branch, $category] = $this->createScopedUser();

        $archiveWithCif = $this->createArchive($user, $branch, $category, 'Archive Dengan CIF');
        $this->attachBusinessReference($archiveWithCif, 'cif', '00100013785');

        $archiveWithoutCif = $this->createArchive($user, $branch, $category, 'Archive Tanpa CIF');

        $this->actingAs($user);

        Livewire::test(ListArchives::class)
            ->assertTableColumnExists('cif')
            ->assertTableColumnFormattedStateSet('cif', '00100013785', $archiveWithCif)
            ->assertTableColumnFormattedStateSet('cif', '-', $archiveWithoutCif);
    }

    public function test_archive_list_search_finds_matching_cif_from_business_references(): void
    {
        [$user, $branch, $category] = $this->createScopedUser();

        $matchingArchive = $this->createArchive($user, $branch, $category, 'Archive Match CIF');
        $this->attachBusinessReference($matchingArchive, 'cif', '001 000 13785');

        $otherArchive = $this->createArchive($user, $branch, $category, 'Archive CIF Lain');
        $this->attachBusinessReference($otherArchive, 'cif', '00900000001');

        $this->actingAs($user);

        Livewire::test(ListArchives::class)
            ->assertCanSeeTableRecords([$matchingArchive, $otherArchive])
            ->searchTable('00100013785')
            ->assertCanSeeTableRecords([$matchingArchive])
            ->assertCanNotSeeTableRecords([$otherArchive]);
    }

    public function test_archive_list_cif_search_respects_archive_visibility_scope(): void
    {
        [$user, $branchOne, $category] = $this->createScopedUser();
        $branchTwo = BranchOffice::query()->create([
            'branch_code' => '02',
            'branch_name' => 'KC Bogor',
        ]);

        $visibleArchive = $this->createArchive($user, $branchOne, $category, 'Archive Cabang Sendiri');
        $this->attachBusinessReference($visibleArchive, 'cif', '00100099999');

        $hiddenArchive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive Cabang Lain',
            'archive_code' => 'ARCHIVE-CABANG-LAIN',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/archive-cabang-lain.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchTwo->id,
            'archive_date' => '2026-04-14',
        ]);
        $this->attachBusinessReference($hiddenArchive, 'cif', '00100013785');

        $this->actingAs($user);

        Livewire::test(ListArchives::class)
            ->assertCanSeeTableRecords([$visibleArchive])
            ->assertCanNotSeeTableRecords([$hiddenArchive])
            ->searchTable('00100013785')
            ->assertCanNotSeeTableRecords([$visibleArchive, $hiddenArchive]);
    }

    public function test_archive_reference_filter_still_matches_non_cif_business_references(): void
    {
        [$user, $branch, $category] = $this->createScopedUser();

        $matchingArchive = $this->createArchive($user, $branch, $category, 'Archive Referensi Match');
        $this->attachBusinessReference($matchingArchive, 'savings_account_no', '001000000000001');

        $otherArchive = $this->createArchive($user, $branch, $category, 'Archive Referensi Lain');
        $this->attachBusinessReference($otherArchive, 'savings_account_no', '001000000000002');

        $this->actingAs($user);

        Livewire::test(ListArchives::class)
            ->filterTable('reference_value', ['value' => '001000000000001'])
            ->assertCanSeeTableRecords([$matchingArchive])
            ->assertCanNotSeeTableRecords([$otherArchive]);
    }

    /**
     * @return array{0: User, 1: BranchOffice, 2: Category}
     */
    private function createScopedUser(): array
    {
        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $user = User::query()->create([
            'name' => 'User Arsip',
            'email' => 'arsip@example.com',
            'username' => 'user-arsip',
            'password' => Hash::make('password'),
            'branch_office_id' => $branch->id,
        ]);

        $this->assignRole($user, 'kearsipan');
        $user->permittedCategories()->attach($category->id);

        return [$user, $branch, $category];
    }

    private function createArchive(User $user, BranchOffice $branch, Category $category, string $name): Archive
    {
        return Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => $name,
            'archive_code' => strtoupper((string) str($name)->slug('_')),
            'archive_description' => 'Archive',
            'archive_path' => 'archives/'.str($name)->slug().'.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-14',
        ]);
    }

    private function attachBusinessReference(Archive $archive, string $referenceType, string $rawValue): void
    {
        $field = CategoryReferenceField::query()->firstOrCreate(
            [
                'category_id' => $archive->archive_category,
                'reference_type' => $referenceType,
            ],
            [
                'label' => str($referenceType)->replace('_', ' ')->title()->toString(),
                'help_text' => null,
                'is_required' => false,
                'is_primary_match_key' => false,
                'normalizer' => 'uppercase_compact',
                'sort_order' => 1,
            ],
        );

        ArchiveBusinessReference::query()->create([
            'archive_id' => $archive->id,
            'category_reference_field_id' => $field->id,
            'reference_type' => $referenceType,
            'raw_value' => $rawValue,
            'normalized_value' => ReferenceNormalizer::normalize($rawValue),
            'source_system' => 'siardi',
            'source_table' => 'archives',
            'source_key_name' => $referenceType,
            'branch_code' => $archive->branchOffice?->branch_code,
        ]);
    }
}
