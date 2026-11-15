<?php

namespace Tests\Feature;

use App\Filament\Resources\ArchiveResource\Pages\CreateArchive;
use App\Filament\Resources\ArchiveResource\Pages\EditArchive;
use App\Filament\Resources\ArchiveResource\Pages\ListArchives;
use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\User;
use App\Support\ArchivePreviewRenderer;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\InteractsWithDwh;
use Tests\TestCase;

class ArchiveFileLifecycleTest extends TestCase
{
    use InteractsWithDwh;
    use RefreshDatabase;

    private BranchOffice $branch;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('archives');
        Storage::fake('public');

        $this->branch = BranchOffice::query()->create(['branch_code' => '01', 'branch_name' => 'KC Karawang']);
        $this->category = Category::query()->create(['category_name' => 'AUDIT', 'category_description' => 'Audit']);
    }

    public function test_same_original_filename_creates_two_private_archives_with_generated_names(): void
    {
        $user = $this->makeUser('kearsipan');

        $first = $this->createArchiveThroughForm($user, $this->pdf('laporan.pdf'));
        $second = $this->createArchiveThroughForm($user, $this->pdf('laporan.pdf'));

        $this->assertNotSame($first->archive_path, $second->archive_path);

        foreach ([$first, $second] as $archive) {
            $this->assertSame('laporan.pdf', $archive->archive_original_name);
            $this->assertSame('archives', $archive->archive_disk);
            $this->assertSame('pdf', $archive->archive_type);
            $this->assertNotSame('laporan.pdf', basename($archive->archive_path));
            $this->assertMatchesRegularExpression('/^[0-9a-z]{26}\.pdf$/', $archive->archive_path);
            Storage::disk('archives')->assertExists($archive->archive_path);
            Storage::disk('public')->assertMissing($archive->archive_path);
            Storage::disk('public')->assertMissing('archives/'.$archive->archive_path);
        }
    }

    public function test_private_archive_disk_is_not_exposed_through_public_storage(): void
    {
        $archive = $this->makeArchive($this->makeUser('kearsipan'));

        $this->assertStringStartsWith(storage_path('app/private'), config('filesystems.disks.archives.root'));
        $this->assertNotSame(config('filesystems.disks.public.root'), config('filesystems.disks.archives.root'));
        $this->assertNotSame(200, $this->get('/storage/'.$archive->archive_path)->getStatusCode());
        $this->assertNotSame(200, $this->get('/storage/archives/'.$archive->archive_path)->getStatusCode());
    }

    public function test_visible_authorized_user_can_preview_and_download(): void
    {
        $user = $this->makeUser('kearsipan');
        $archive = $this->makeArchive($user, 'Laporan Audit 2026.pdf', '%PDF-1.4 content');

        $preview = $this->actingAs($user)->get(route('archives.file.show', $archive));
        $preview->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Laporan Audit 2026.pdf', $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $preview->headers->get('Cache-Control'));

        $download = $this->actingAs($user)->get(route('archives.file.download', $archive));
        $download->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_guest_and_users_without_permission_or_scope_cannot_fetch_files(): void
    {
        $owner = $this->makeUser('kearsipan');
        $archive = $this->makeArchive($owner);

        $this->get(route('archives.file.show', $archive))->assertRedirect('/arsip_digital/login');

        // Same branch and category, but no view_archive permission.
        $this->actingAs($this->makeUser('nonaktif'))->get(route('archives.file.show', $archive))->assertForbidden();

        $otherBranch = BranchOffice::query()->create(['branch_code' => '02', 'branch_name' => 'KC Bogor']);
        $this->actingAs($this->makeUser('kearsipan', $otherBranch))->get(route('archives.file.download', $archive))->assertNotFound();

        $this->actingAs($this->makeUser('kearsipan', categories: []))->get(route('archives.file.show', $archive))->assertNotFound();

        // Branch-level super_admin bypasses policies via Gate::before but must still respect branch scope.
        $this->actingAs($this->makeUser('super_admin', $otherBranch))->get(route('archives.file.show', $archive))->assertNotFound();
    }

    public function test_head_office_super_admin_can_fetch_any_archive(): void
    {
        $archive = $this->makeArchive($this->makeUser('kearsipan'));
        $headOffice = BranchOffice::query()->create(['branch_code' => '00', 'branch_name' => 'Kantor Pusat']);

        $this->actingAs($this->makeUser('super_admin', $headOffice, []))
            ->get(route('archives.file.show', $archive))
            ->assertOk();
    }

    public function test_policy_view_enforces_row_level_scope(): void
    {
        $archive = $this->makeArchive($this->makeUser('kearsipan'));
        $otherBranch = BranchOffice::query()->create(['branch_code' => '02', 'branch_name' => 'KC Bogor']);

        $this->assertTrue($this->makeUser('kearsipan')->can('view', $archive));
        $this->assertFalse($this->makeUser('kearsipan', $otherBranch)->can('view', $archive));
        $this->assertFalse($this->makeUser('kearsipan', categories: [])->can('update', $archive));
    }

    public function test_preview_renderer_and_endpoint_support_each_preview_category(): void
    {
        $user = $this->makeUser('kearsipan');

        $cases = [
            ['scan.png', 'png', 'image/png', '<img'],
            ['rekaman.mp4', 'mp4', 'video/mp4', '<video'],
            ['catatan.txt', 'txt', 'text/plain; charset=UTF-8', 'isi catatan'],
            ['dokumen.pdf', 'pdf', 'application/pdf', 'data-siardi-pdf-preview'],
        ];

        foreach ($cases as [$name, $type, $mime, $marker]) {
            $archive = $this->makeArchive($user, $name, $type === 'txt' ? 'isi catatan' : 'binary');
            $html = ArchivePreviewRenderer::render($archive)->toHtml();

            $this->assertStringContainsString($marker, $html);
            $this->assertStringContainsString(route('archives.file.show', $archive), $html);
            $this->assertStringNotContainsString('/storage/', $html);
            $this->actingAs($user)->get(route('archives.file.show', $archive))->assertOk()->assertHeader('Content-Type', $mime);
        }

        $docx = $this->makeArchive($user, 'surat.docx', 'docx');
        $this->assertStringContainsString(route('archives.file.download', $docx), ArchivePreviewRenderer::render($docx)->toHtml());
        $response = $this->actingAs($user)->get(route('archives.file.show', $docx));
        $response->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_missing_physical_file_returns_safe_response_and_logs_context(): void
    {
        Log::spy();
        $user = $this->makeUser('kearsipan');
        $archive = $this->makeArchive($user);
        Storage::disk('archives')->delete($archive->archive_path);

        $response = $this->actingAs($user)->get(route('archives.file.show', $archive));

        $response->assertNotFound()->assertSee('File arsip tidak ditemukan');
        $this->assertStringNotContainsString($archive->archive_path, $response->getContent());
        $this->assertStringNotContainsString(storage_path(), $response->getContent());

        Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message, array $context): bool => $level === 'warning'
            && $context['operation'] === 'preview'
            && $context['archive_id'] === $archive->id
            && $context['user_id'] === $user->id
            && str_starts_with($context['error_code'], 'ARC-')
            && str_contains($response->getContent(), $context['error_code']));

        $this->assertStringContainsString('File arsip tidak tersedia', ArchivePreviewRenderer::render($archive)->toHtml());
    }

    public function test_replacing_file_stores_new_file_then_removes_previous_file(): void
    {
        $user = $this->makeUser('kearsipan');
        $archive = $this->makeArchive($user, 'lama.pdf');
        $oldPath = $archive->archive_path;

        Livewire::actingAs($user)
            ->test(EditArchive::class, ['record' => $archive->getRouteKey()])
            ->set('data.archive_path', [])
            ->fillForm(['archive_path' => $this->pdf('baru.pdf')])
            ->call('save')
            ->assertHasNoFormErrors();

        $archive->refresh();
        $this->assertNotSame($oldPath, $archive->archive_path);
        $this->assertSame('baru.pdf', $archive->archive_original_name);
        Storage::disk('archives')->assertExists($archive->archive_path);
        Storage::disk('archives')->assertMissing($oldPath);
    }

    public function test_replacing_legacy_public_file_moves_archive_to_private_disk(): void
    {
        $user = $this->makeUser('kearsipan');
        $archive = $this->makeLegacyArchive($user, 'archives/legacy-lama.pdf');

        Livewire::actingAs($user)
            ->test(EditArchive::class, ['record' => $archive->getRouteKey()])
            ->set('data.archive_path', [])
            ->fillForm(['archive_path' => $this->pdf('pengganti.pdf')])
            ->call('save')
            ->assertHasNoFormErrors();

        $archive->refresh();
        $this->assertSame('archives', $archive->archive_disk);
        Storage::disk('archives')->assertExists($archive->archive_path);
        Storage::disk('public')->assertMissing('archives/legacy-lama.pdf');
    }

    public function test_failed_persistence_during_replacement_keeps_old_file_and_removes_new_file(): void
    {
        Log::spy();
        $user = $this->makeUser('kearsipan');
        $archive = $this->makeArchive($user, 'lama.pdf');
        $oldPath = $archive->archive_path;

        Archive::updating(fn () => throw new RuntimeException('SQLSTATE[HY000] simulated outage'));

        Livewire::actingAs($user)
            ->test(EditArchive::class, ['record' => $archive->getRouteKey()])
            ->set('data.archive_path', [])
            ->fillForm(['archive_path' => $this->pdf('baru.pdf')])
            ->call('save')
            ->assertNotified('Perubahan arsip gagal disimpan')
            ->assertDontSee('SQLSTATE');

        Archive::flushEventListeners();

        $this->assertSame($oldPath, $archive->fresh()->archive_path);
        Storage::disk('archives')->assertExists($oldPath);
        $this->assertSame([$oldPath], Storage::disk('archives')->allFiles());

        Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message, array $context): bool => $level === 'error'
            && $context['operation'] === 'replace'
            && $context['exception_class'] === RuntimeException::class
            && str_starts_with($context['error_code'], 'UPL-'));
    }

    public function test_failed_persistence_during_create_leaves_no_unmanaged_file(): void
    {
        $user = $this->makeUser('kearsipan');

        Archive::creating(fn () => throw new RuntimeException('simulated outage'));

        Livewire::actingAs($user)
            ->test(CreateArchive::class)
            ->fillForm($this->formData($this->pdf('laporan.pdf')))
            ->call('create')
            ->assertNotified('Arsip gagal disimpan')
            ->assertSet('data.archive_path', []);

        Archive::flushEventListeners();

        $this->assertSame(0, Archive::query()->count());
        $this->assertSame([], Storage::disk('archives')->allFiles());
    }

    public function test_storage_failure_during_upload_shows_safe_message_with_error_code(): void
    {
        Log::spy();
        $user = $this->makeUser('kearsipan');

        // Point the archive disk at an unwritable root to simulate a storage failure.
        config()->set('filesystems.disks.archives', ['driver' => 'local', 'root' => '/proc/siardi-unwritable', 'throw' => true]);
        Storage::forgetDisk('archives');

        $component = Livewire::actingAs($user)
            ->test(CreateArchive::class)
            ->fillForm($this->formData($this->pdf('laporan.pdf')))
            ->call('create')
            ->assertHasErrors(['data.archive_path']);

        $message = $component->errors()->first('data.archive_path');
        $this->assertMatchesRegularExpression('/^File tidak dapat disimpan\. .*UPL-[A-Z0-9]{8}\.$/', $message);
        $this->assertStringNotContainsString('/proc', $message);
        $this->assertSame(0, Archive::query()->count());

        Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message, array $context): bool => $level === 'error'
            && $context['operation'] === 'create'
            && $context['original_name'] === 'laporan.pdf'
            && $context['user_id'] === $user->id
            && $context['branch_id'] === $this->branch->id);
    }

    public function test_required_message_explains_failed_upload_instead_of_generic_required(): void
    {
        Livewire::actingAs($this->makeUser('kearsipan'))
            ->test(CreateArchive::class)
            ->fillForm($this->formData(null))
            ->call('create')
            ->assertHasFormErrors(['archive_path' => 'required'])
            ->assertSee('Jika upload sebelumnya gagal');
    }

    public function test_intentional_delete_removes_record_and_file(): void
    {
        $admin = $this->makeHeadOfficeSuperAdmin();
        $single = $this->makeArchive($admin);
        $bulk = $this->makeArchive($admin);
        $legacy = $this->makeLegacyArchive($admin, 'archives/legacy-delete.pdf');

        Livewire::actingAs($admin)
            ->test(EditArchive::class, ['record' => $single->getRouteKey()])
            ->callAction(DeleteAction::class);

        Livewire::actingAs($admin)
            ->test(ListArchives::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$bulk, $legacy]);

        $this->assertSame(0, Archive::query()->count());
        $this->assertSame([], Storage::disk('archives')->allFiles());
        Storage::disk('public')->assertMissing('archives/legacy-delete.pdf');
    }

    public function test_master_data_deletion_does_not_cascade_into_archives_or_files(): void
    {
        $uploader = $this->makeUser('kearsipan');
        $archive = $this->makeArchive($uploader);

        $uploader->delete();
        $this->assertNull($archive->fresh()->archive_user);

        try {
            $this->category->delete();
            $this->fail('Deleting a category that still has archives must be rejected.');
        } catch (QueryException) {
        }

        $this->branch->delete();

        $this->assertNotNull($archive->fresh());
        Storage::disk('archives')->assertExists($archive->archive_path);
    }

    public function test_legacy_migration_dry_run_changes_nothing(): void
    {
        $archive = $this->makeLegacyArchive($this->makeUser('kearsipan'), 'archives/legacy-dry.pdf');

        $this->artisan('siardi:migrate-legacy-archive-files', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('public', $archive->fresh()->archive_disk);
        $this->assertSame('archives/legacy-dry.pdf', $archive->fresh()->archive_path);
        Storage::disk('public')->assertExists('archives/legacy-dry.pdf');
        $this->assertSame([], Storage::disk('archives')->allFiles());
    }

    public function test_legacy_migration_moves_files_is_idempotent_and_skips_missing_files(): void
    {
        $user = $this->makeUser('kearsipan');
        $archive = $this->makeLegacyArchive($user, 'archives/12.345 - kredit lama.pdf', 'legacy pdf');
        $missing = $this->makeLegacyArchive($user, 'archives/hilang.pdf');
        Storage::disk('public')->delete('archives/hilang.pdf');

        $this->artisan('siardi:migrate-legacy-archive-files')->assertSuccessful();

        $archive->refresh();
        $this->assertSame('archives', $archive->archive_disk);
        $this->assertSame('12.345 - kredit lama.pdf', $archive->archive_original_name);
        $this->assertSame('legacy pdf', Storage::disk('archives')->get($archive->archive_path));
        Storage::disk('public')->assertMissing('archives/12.345 - kredit lama.pdf');

        $this->assertSame('public', $missing->fresh()->archive_disk);
        $this->assertSame('archives/hilang.pdf', $missing->fresh()->archive_path);
        $this->assertNull($missing->fresh()->archive_original_name);

        $migratedPath = $archive->archive_path;
        $this->artisan('siardi:migrate-legacy-archive-files')->assertSuccessful();

        $this->assertSame($migratedPath, $archive->fresh()->archive_path);
        $this->assertSame([$migratedPath], Storage::disk('archives')->allFiles());

        $this->actingAs($user)->get(route('archives.file.download', $archive))->assertOk();
    }

    public function test_dwh_outage_keeps_archive_file_and_references_for_later_rematch(): void
    {
        Log::spy();
        $this->setUpDwhConnection(); // DWH reachable config, but target tables missing => query failure.

        $tabungan = Category::query()->create(['category_name' => 'TABUNGAN', 'category_description' => 'Tabungan']);
        $field = CategoryReferenceField::query()->create([
            'category_id' => $tabungan->id,
            'reference_type' => 'savings_account_no',
            'label' => 'Nomor Rekening Tabungan',
            'input_type' => 'text',
            'sort_order' => 20,
            'is_required' => true,
            'is_primary_match_key' => true,
            'normalizer' => 'uppercase_compact',
            'dwh_entity' => 'savings',
        ]);
        $user = $this->makeUser('kearsipan', categories: [$tabungan->id]);

        Livewire::actingAs($user)
            ->test(CreateArchive::class)
            ->fillForm([
                ...$this->formData($this->pdf('tabungan.pdf')),
                'archive_category' => $tabungan->id,
            ])
            ->set("data.business_references.{$field->id}", '001-000-111')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Arsip tersimpan, pencocokan DWH tertunda');

        $archive = Archive::query()->sole();
        Storage::disk('archives')->assertExists($archive->archive_path);

        $reference = ArchiveBusinessReference::query()->sole();
        $this->assertSame('001-000-111', $reference->raw_value);
        $this->assertNull($reference->matched_source_key);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['archive_id'] === $archive->id
            && $context['exception_class'] === QueryException::class);
    }

    private function createArchiveThroughForm(User $user, UploadedFile $file): Archive
    {
        Livewire::actingAs($user)
            ->test(CreateArchive::class)
            ->fillForm($this->formData($file))
            ->call('create')
            ->assertHasNoFormErrors();

        return Archive::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?UploadedFile $file): array
    {
        return [
            'archive_name' => 'Arsip Uji',
            'archive_category' => $this->category->id,
            'archive_date' => '2026-10-12',
            'archive_description' => 'Arsip untuk pengujian',
            'archive_path' => $file,
        ];
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
    }

    /**
     * @param  array<int, int>|null  $categories
     */
    private function makeUser(string $role, ?BranchOffice $branch = null, ?array $categories = null): User
    {
        $user = User::factory()->create(['branch_office_id' => ($branch ?? $this->branch)->id]);
        $this->assignRole($user, $role);
        $user->permittedCategories()->attach($categories ?? [$this->category->id]);

        return $user;
    }

    private function makeHeadOfficeSuperAdmin(): User
    {
        $headOffice = BranchOffice::query()->create(['branch_code' => '00', 'branch_name' => 'Kantor Pusat']);

        return $this->makeUser('super_admin', $headOffice);
    }

    private function makeArchive(User $user, string $originalName = 'dokumen.pdf', string $content = '%PDF-1.4'): Archive
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $path = strtolower((string) str()->ulid()).'.'.$extension;
        Storage::disk('archives')->put($path, $content);

        return Archive::query()->create([
            'archive_category' => $this->category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Arsip '.$originalName,
            'archive_description' => 'Arsip',
            'archive_path' => $path,
            'archive_original_name' => $originalName,
            'archive_disk' => 'archives',
            'archive_type' => $extension,
            'archive_branch_office' => $this->branch->id,
            'archive_date' => '2026-10-12',
        ]);
    }

    private function makeLegacyArchive(User $user, string $legacyPath, string $content = '%PDF-1.4'): Archive
    {
        Storage::disk('public')->put($legacyPath, $content);

        return Archive::query()->create([
            'archive_category' => $this->category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Legacy '.$legacyPath,
            'archive_description' => 'Arsip lama',
            'archive_path' => $legacyPath,
            'archive_disk' => 'public',
            'archive_type' => pathinfo($legacyPath, PATHINFO_EXTENSION),
            'archive_branch_office' => $this->branch->id,
            'archive_date' => '2026-10-12',
        ]);
    }
}
