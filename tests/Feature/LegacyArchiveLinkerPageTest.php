<?php

namespace Tests\Feature;

use App\Filament\Pages\LegacyArchiveLinker;
use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class LegacyArchiveLinkerPageTest extends TestCase
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

    public function test_selecting_archive_loading_candidates_and_saving_references_updates_the_workspace(): void
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $cifField = CategoryReferenceField::query()->create([
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
        $user->permittedCategories()->attach($category->id);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branch->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'Kantor Pusat Operasional',
            'is_active' => true,
        ]);

        DB::connection('dwh')->table('raw_savings')->insert([
            '_row_key' => 'ROW-1',
            'as_of_date' => '2026-04-10',
            'locationid' => '001',
            'nocif' => '00100013785',
            'norekening' => '001000000000001',
            'status_dokumen' => 'Active',
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Legacy Savings Archive',
            'archive_code' => 'LEGACY-001',
            'archive_description' => 'Legacy archive',
            'archive_path' => 'archives/legacy-savings.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        Livewire::actingAs($user)
            ->test(LegacyArchiveLinker::class)
            ->call('selectArchive', $archive->id)
            ->assertSet('selectedArchiveId', $archive->id)
            ->assertDispatched('open-modal', id: LegacyArchiveLinker::LINKING_MODAL_ID)
            ->assertSee('Legacy Savings Archive')
            ->set("referenceInputs.{$primaryField->id}", '001000000000001')
            ->call('lookupCandidates')
            ->assertSet('candidateResults.0.source_key', 'ROW-1')
            ->call('applyCandidate', 0)
            ->assertSet("referenceInputs.{$cifField->id}", '00100013785')
            ->call('saveReferences')
            ->assertDispatched('close-modal', id: LegacyArchiveLinker::LINKING_MODAL_ID)
            ->assertSet('selectedArchiveId', null);

        $this->assertDatabaseHas('archive_business_references', [
            'archive_id' => $archive->id,
            'category_reference_field_id' => $cifField->id,
            'raw_value' => '00100013785',
        ]);

        $this->assertDatabaseHas('archive_business_references', [
            'archive_id' => $archive->id,
            'category_reference_field_id' => $primaryField->id,
            'raw_value' => '001000000000001',
            'matched_table' => 'raw_savings',
            'matched_source_key' => 'ROW-1',
        ]);
    }

    public function test_branch_user_with_supported_category_can_open_legacy_linking_page(): void
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
        $user->permittedCategories()->attach($category->id);

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertOk();
    }

    public function test_user_without_supported_category_cannot_open_legacy_linking_page(): void
    {
        Category::query()->create([
            'category_name' => 'AUDIT',
            'category_description' => 'Audit',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);

        $this->actingAs($user)
            ->get('/arsip_digital/legacy-archive-linker')
            ->assertForbidden();
    }

    public function test_select_archive_fails_for_archive_outside_user_scope(): void
    {
        $category = Category::query()->create([
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
        $user->permittedCategories()->attach($category->id);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Out of Scope Archive',
            'archive_code' => 'OOS-001',
            'archive_description' => 'Hidden',
            'archive_path' => 'archives/out-of-scope.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branchTwo->id,
            'archive_date' => '2026-04-10',
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test(LegacyArchiveLinker::class)
            ->call('selectArchive', $archive->id);
    }

    public function test_save_references_fails_when_primary_match_key_is_not_found(): void
    {
        [$user, $archive, $cifField, $primaryField] = $this->createTabunganLegacyLinkingContext();

        Livewire::actingAs($user)
            ->test(LegacyArchiveLinker::class)
            ->call('selectArchive', $archive->id)
            ->set("referenceInputs.{$cifField->id}", '00100013785')
            ->set("referenceInputs.{$primaryField->id}", '999000000000999')
            ->call('saveReferences')
            ->assertHasErrors(["referenceInputs.{$primaryField->id}"]);

        $this->assertDatabaseCount('archive_business_references', 0);
    }

    public function test_save_references_fails_when_secondary_field_does_not_match_target_row(): void
    {
        [$user, $archive, $cifField, $primaryField] = $this->createTabunganLegacyLinkingContext();

        Livewire::actingAs($user)
            ->test(LegacyArchiveLinker::class)
            ->call('selectArchive', $archive->id)
            ->set("referenceInputs.{$primaryField->id}", '001000000000001')
            ->set("referenceInputs.{$cifField->id}", '99999999999')
            ->call('saveReferences')
            ->assertHasErrors(["referenceInputs.{$cifField->id}"]);

        $this->assertDatabaseCount('archive_business_references', 0);
    }

    public function test_save_references_fails_when_branch_mapping_is_inactive(): void
    {
        [$user, $archive, $cifField, $primaryField, $mapping] = $this->createTabunganLegacyLinkingContext();
        $mapping->update(['is_active' => false]);

        Livewire::actingAs($user)
            ->test(LegacyArchiveLinker::class)
            ->call('selectArchive', $archive->id)
            ->set("referenceInputs.{$cifField->id}", '00100013785')
            ->set("referenceInputs.{$primaryField->id}", '001000000000001')
            ->call('saveReferences')
            ->assertHasErrors(["referenceInputs.{$primaryField->id}"]);

        $this->assertDatabaseCount('archive_business_references', 0);
    }

    public function test_kredit_candidate_lookup_can_use_alt_rekening_search_helper(): void
    {
        $category = Category::query()->create([
            'category_name' => 'KREDIT',
            'category_description' => 'Kredit',
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
            'dwh_entity' => 'loans',
        ]);

        $primaryField = CategoryReferenceField::query()->create([
            'category_id' => $category->id,
            'reference_type' => 'loan_account_no',
            'label' => 'Nomor Rekening Kredit',
            'input_type' => 'text',
            'sort_order' => 20,
            'is_required' => true,
            'is_primary_match_key' => true,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'loans',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => '01',
            'branch_name' => 'KC Karawang',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);
        $user->permittedCategories()->attach($category->id);

        DwhBranchMapping::query()->create([
            'branch_office_id' => $branch->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'KC Karawang',
            'is_active' => true,
        ]);

        DB::connection('dwh')->table('raw_loans')->insert([
            '_row_key' => 'LOAN-ALT-1',
            'as_of_date' => '2026-04-12',
            'locationid' => '001',
            'nocif' => '00100055555',
            'id' => '1234567890123456',
            'noalt' => '1234567890',
            'noperjanjiankredit' => 'PK20261234',
            'status_dokumen' => 'Active',
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Kredit 12.345.67890',
            'archive_code' => 'KRD-ALT-001',
            'archive_description' => 'Legacy kredit archive',
            'archive_path' => 'archives/12.345.67890 - kredit.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-12',
        ]);

        Livewire::actingAs($user)
            ->test(LegacyArchiveLinker::class)
            ->call('selectArchive', $archive->id)
            ->assertSet('candidateSearchInputs.loan_alt_account_no', '1234567890')
            ->assertDontSee('Nomor Perjanjian Kredit')
            ->set('candidateSearchInputs.loan_alt_account_no', '12.345.67890')
            ->call('lookupCandidates')
            ->assertSet('candidateResults.0.source_key', 'LOAN-ALT-1')
            ->call('applyCandidate', 0)
            ->assertSet("referenceInputs.{$primaryField->id}", '1234567890123456');
    }

    /**
     * @return array{0: User, 1: Archive, 2: CategoryReferenceField, 3: CategoryReferenceField, 4: DwhBranchMapping}
     */
    private function createTabunganLegacyLinkingContext(): array
    {
        $category = Category::query()->create([
            'category_name' => 'TABUNGAN',
            'category_description' => 'Tabungan',
        ]);

        $cifField = CategoryReferenceField::query()->create([
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
            'branch_name' => 'KC Karawang',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);
        $user->permittedCategories()->attach($category->id);

        $mapping = DwhBranchMapping::query()->create([
            'branch_office_id' => $branch->id,
            'siardi_branch_code' => '01',
            'dwh_location_code' => '001',
            'dwh_location_name' => 'KC Karawang',
            'is_active' => true,
        ]);

        DB::connection('dwh')->table('raw_savings')->insert([
            '_row_key' => 'ROW-CTX-1',
            'as_of_date' => '2026-04-10',
            'locationid' => '001',
            'nocif' => '00100013785',
            'norekening' => '001000000000001',
            'status_dokumen' => 'Active',
        ]);

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Legacy Savings Archive',
            'archive_code' => 'LEGACY-CTX-001',
            'archive_description' => 'Legacy archive',
            'archive_path' => 'archives/legacy-savings-context.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-10',
        ]);

        return [$user, $archive, $cifField, $primaryField, $mapping];
    }
}
