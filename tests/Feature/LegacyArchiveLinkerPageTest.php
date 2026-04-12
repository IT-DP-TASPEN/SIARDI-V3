<?php

namespace Tests\Feature;

use App\Filament\Pages\LegacyArchiveLinker;
use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Models\Role;
use App\Models\User;
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

        $adminRole = Role::query()->create([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);
        $user->roles()->attach($adminRole->id);
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
}
