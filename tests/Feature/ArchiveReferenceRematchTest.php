<?php

namespace Tests\Feature;

use App\Jobs\RematchArchiveReferencesJob;
use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Models\User;
use App\Services\ArchiveBusinessReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class ArchiveReferenceRematchTest extends TestCase
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

    public function test_rematch_archive_updates_previous_unmatched_reference_after_target_row_arrives(): void
    {
        [$archive, $field] = $this->createSavingsArchiveContext();

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, [
            $field->id => '001000000000001',
        ]);

        $reference = $archive->businessReferences()->firstOrFail();

        $this->assertNull($reference->matched_source_key);

        DB::connection('dwh')->table('raw_savings')->insert([
            '_row_key' => 'ROW-HPLUS1',
            'as_of_date' => '2026-04-13',
            'locationid' => '001',
            'nocif' => '00100013785',
            'norekening' => '001000000000001',
            'status_dokumen' => 'Active',
        ]);

        $result = app(ArchiveBusinessReferenceService::class)->rematchArchive($archive->fresh());

        $reference->refresh();

        $this->assertSame(1, $result['updated_references']);
        $this->assertSame('raw_savings', $reference->matched_table);
        $this->assertSame('ROW-HPLUS1', $reference->matched_source_key);
    }

    public function test_rematch_command_dispatches_jobs_only_for_unmatched_backlog_by_default(): void
    {
        [$archiveOne, $field] = $this->createSavingsArchiveContext();
        [$archiveTwo] = $this->createSavingsArchiveContext('001000000000002', 'ROW-MATCHED');

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archiveOne, [
            $field->id => '001000000000001',
        ]);

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archiveTwo, [
            $field->id => '001000000000002',
        ]);

        Queue::fake();

        $this->artisan('siardi:rematch-references')
            ->expectsOutput('Queued 1 rematch job(s).')
            ->assertSuccessful();

        Queue::assertPushed(RematchArchiveReferencesJob::class, fn (RematchArchiveReferencesJob $job): bool => $job->archiveId === $archiveOne->id);
        Queue::assertNotPushed(RematchArchiveReferencesJob::class, fn (RematchArchiveReferencesJob $job): bool => $job->archiveId === $archiveTwo->id);
    }

    public function test_rematch_archive_canonicalizes_savings_alt_account_number_to_main_account(): void
    {
        [$archive, $field] = $this->createSavingsArchiveContext(
            accountNo: '001000000000001',
            existingRowKey: 'ROW-SAVINGS-ALT-1',
            alternateAccountNo: '9988776655',
        );

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, [
            $field->id => '9988776655',
        ]);

        $reference = $archive->businessReferences()->firstOrFail();

        $this->assertNull($reference->matched_source_key);
        $this->assertSame('9988776655', $reference->raw_value);
        $this->assertSame('9988776655', $reference->normalized_value);

        $result = app(ArchiveBusinessReferenceService::class)->rematchArchive($archive->fresh());

        $reference->refresh();

        $this->assertSame(1, $result['updated_references']);
        $this->assertSame('001000000000001', $reference->raw_value);
        $this->assertSame('001000000000001', $reference->normalized_value);
        $this->assertSame('raw_savings', $reference->matched_table);
        $this->assertSame('ROW-SAVINGS-ALT-1', $reference->matched_source_key);
    }

    public function test_rematch_archive_canonicalizes_kredit_alt_account_number_to_main_account(): void
    {
        [$archive, $field] = $this->createLoanArchiveContext(
            accountNo: '1234567890123456',
            existingRowKey: 'LOAN-ALT-1',
            alternateAccountNo: '1234567890',
        );

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, [
            $field->id => '12.345.67890',
        ]);

        $reference = $archive->businessReferences()->firstOrFail();

        $this->assertNull($reference->matched_source_key);
        $this->assertSame('12.345.67890', $reference->raw_value);
        $this->assertSame('12.345.67890', $reference->normalized_value);

        $result = app(ArchiveBusinessReferenceService::class)->rematchArchive($archive->fresh());

        $reference->refresh();

        $this->assertSame(1, $result['updated_references']);
        $this->assertSame('1234567890123456', $reference->raw_value);
        $this->assertSame('1234567890123456', $reference->normalized_value);
        $this->assertSame('raw_loans', $reference->matched_table);
        $this->assertSame('LOAN-ALT-1', $reference->matched_source_key);
    }

    public function test_rematch_archive_keeps_alt_account_number_unchanged_when_no_canonical_row_exists(): void
    {
        [$archive, $field] = $this->createLoanArchiveContext();

        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, [
            $field->id => '12.345.67890',
        ]);

        $reference = $archive->businessReferences()->firstOrFail();

        $result = app(ArchiveBusinessReferenceService::class)->rematchArchive($archive->fresh());

        $reference->refresh();

        $this->assertSame(0, $result['updated_references']);
        $this->assertSame(0, $result['matched_references']);
        $this->assertSame(1, $result['unmatched_references']);
        $this->assertSame('12.345.67890', $reference->raw_value);
        $this->assertSame('12.345.67890', $reference->normalized_value);
        $this->assertNull($reference->matched_table);
        $this->assertNull($reference->matched_source_key);
    }

    /**
     * @return array{0: Archive, 1: CategoryReferenceField}
     */
    private function createSavingsArchiveContext(
        string $accountNo = '001000000000001',
        ?string $existingRowKey = null,
        ?string $alternateAccountNo = null,
    ): array {
        $category = Category::query()->firstOrCreate(
            ['category_name' => 'TABUNGAN'],
            ['category_description' => 'Tabungan'],
        );

        $field = CategoryReferenceField::query()->firstOrCreate(
            [
                'category_id' => $category->id,
                'reference_type' => 'savings_account_no',
            ],
            [
                'label' => 'Nomor Rekening Tabungan',
                'input_type' => 'text',
                'sort_order' => 20,
                'is_required' => true,
                'is_primary_match_key' => true,
                'normalizer' => 'uppercase_compact',
                'dwh_entity' => 'savings',
            ],
        );

        $branch = BranchOffice::query()->firstOrCreate(
            ['branch_code' => '01'],
            ['branch_name' => 'KC Karawang'],
        );

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);

        DwhBranchMapping::query()->firstOrCreate(
            ['branch_office_id' => $branch->id],
            [
                'siardi_branch_code' => '01',
                'dwh_location_code' => '001',
                'dwh_location_name' => 'KC Karawang',
                'is_active' => true,
            ],
        );

        if ($existingRowKey) {
            DB::connection('dwh')->table('raw_savings')->insert([
                '_row_key' => $existingRowKey,
                'as_of_date' => '2026-04-12',
                'locationid' => '001',
                'nocif' => '00100013786',
                'norekening' => $accountNo,
                'noalt' => $alternateAccountNo,
                'status_dokumen' => 'Active',
            ]);
        }

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive '.$accountNo,
            'archive_description' => 'Archive',
            'archive_path' => 'archives/'.$accountNo.'.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-12',
        ]);

        return [$archive, $field];
    }

    /**
     * @return array{0: Archive, 1: CategoryReferenceField}
     */
    private function createLoanArchiveContext(
        string $accountNo = '1234567890123456',
        ?string $existingRowKey = null,
        ?string $alternateAccountNo = null,
    ): array {
        $category = Category::query()->firstOrCreate(
            ['category_name' => 'KREDIT'],
            ['category_description' => 'Kredit'],
        );

        $field = CategoryReferenceField::query()->firstOrCreate(
            [
                'category_id' => $category->id,
                'reference_type' => 'loan_account_no',
            ],
            [
                'label' => 'Nomor Rekening Kredit',
                'input_type' => 'text',
                'sort_order' => 20,
                'is_required' => true,
                'is_primary_match_key' => true,
                'normalizer' => 'uppercase_compact',
                'dwh_entity' => 'loans',
            ],
        );

        $branch = BranchOffice::query()->firstOrCreate(
            ['branch_code' => '01'],
            ['branch_name' => 'KC Karawang'],
        );

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);

        DwhBranchMapping::query()->firstOrCreate(
            ['branch_office_id' => $branch->id],
            [
                'siardi_branch_code' => '01',
                'dwh_location_code' => '001',
                'dwh_location_name' => 'KC Karawang',
                'is_active' => true,
            ],
        );

        if ($existingRowKey) {
            DB::connection('dwh')->table('raw_loans')->insert([
                '_row_key' => $existingRowKey,
                'as_of_date' => '2026-04-12',
                'locationid' => '001',
                'nocif' => '00100013786',
                'id' => $accountNo,
                'noalt' => $alternateAccountNo,
                'noperjanjiankredit' => 'PK20260001',
                'status_dokumen' => 'Active',
            ]);
        }

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Archive '.$accountNo,
            'archive_description' => 'Archive',
            'archive_path' => 'archives/'.$accountNo.'.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $branch->id,
            'archive_date' => '2026-04-12',
        ]);

        return [$archive, $field];
    }
}
