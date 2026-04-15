<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyArchiveCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_filament_panel(): void
    {
        $this->get('/')->assertRedirect('/arsip_digital');
    }

    public function test_custom_login_page_renders_under_filament_v5(): void
    {
        $this->get('/arsip_digital/login')
            ->assertOk()
            ->assertSee('Username');
    }

    public function test_legacy_archive_without_business_references_can_still_be_viewed(): void
    {
        [$branch, $category, $user] = $this->createArchiveUserContext();

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Legacy Kredit Archive',
            'archive_code' => 'OLD-001',
            'archive_description' => 'Legacy archive without references',
            'archive_path' => 'archives/legacy.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '0003-06-22',
        ]);

        $this->actingAs($user)
            ->get("/arsip_digital/archives/{$archive->id}")
            ->assertOk()
            ->assertSee('Legacy Kredit Archive')
            ->assertSee('Legacy archive without references');
    }

    public function test_archive_index_create_and_edit_pages_render_under_filament_v5(): void
    {
        [$branch, $category, $user] = $this->createArchiveUserContext();

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Editable Archive',
            'archive_code' => 'EDIT-001',
            'archive_description' => 'Archive used for edit page rendering',
            'archive_path' => 'archives/editable.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital/archives')
            ->assertOk()
            ->assertSee('Editable Archive');

        $this->actingAs($user)
            ->get('/arsip_digital/archives/create')
            ->assertOk()
            ->assertSee('Nama Arsip');

        $this->actingAs($user)
            ->get("/arsip_digital/archives/{$archive->id}/edit")
            ->assertOk()
            ->assertSee('Editable Archive');
    }

    public function test_kredit_view_hides_deprecated_loan_contract_reference(): void
    {
        [$branch, $category, $user] = $this->createArchiveUserContext();

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Hidden Contract Archive',
            'archive_code' => 'HIDE-001',
            'archive_description' => 'Archive with deprecated reference',
            'archive_path' => 'archives/hide-contract.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        $field = CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'loan_contract_no',
            'label' => 'Nomor Perjanjian Kredit',
            'input_type' => 'text',
            'sort_order' => 30,
            'is_required' => false,
            'is_primary_match_key' => false,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'loans',
        ]);

        ArchiveBusinessReference::query()->create([
            'archive_id' => $archive->id,
            'category_reference_field_id' => $field->id,
            'reference_type' => 'loan_contract_no',
            'raw_value' => 'PK-LEGACY-001',
            'normalized_value' => 'PK-LEGACY-001',
            'source_system' => 'siardi',
            'source_table' => 'archives',
            'source_key_name' => 'loan_contract_no',
            'branch_code' => $branch->branch_code,
        ]);

        $this->actingAs($user)
            ->get("/arsip_digital/archives/{$archive->id}")
            ->assertOk()
            ->assertDontSee('Nomor Perjanjian Kredit')
            ->assertDontSee('PK-LEGACY-001');
    }

    public function test_archive_view_uses_pdfjs_preview_shell_for_pdf_files(): void
    {
        Storage::fake('public');

        [$branch, $category, $user] = $this->createArchiveUserContext();

        Storage::disk('public')->put('archives/view-preview.pdf', '%PDF-1.4 preview');

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'PDF.js Archive View',
            'archive_code' => 'VIEW-001',
            'archive_description' => 'Archive view preview',
            'archive_path' => 'archives/view-preview.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-12',
        ]);

        $this->actingAs($user)
            ->get("/arsip_digital/archives/{$archive->id}")
            ->assertOk()
            ->assertSee('PDF.js Archive View')
            ->assertSee('data-siardi-pdf-preview', false)
            ->assertSee('data-role="canvas"', false)
            ->assertSee('/storage/archives/view-preview.pdf', false)
            ->assertSee('Fit Width');
    }

    /**
     * @return array{0: BranchOffice, 1: Category, 2: User}
     */
    private function createArchiveUserContext(): array
    {
        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'Kantor Pusat Operasional',
        ]);

        $category = Category::query()->create([
            'category_name' => 'KREDIT',
            'category_description' => 'Dokumen kredit',
        ]);

        $user = User::query()->create([
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'username' => 'legacy-user',
            'password' => Hash::make('password'),
            'branch_office_id' => $branch->id,
        ]);

        $this->assignRole($user, 'it_admin');
        $user->permittedCategories()->attach($category->id);

        return [$branch, $category, $user];
    }
}
