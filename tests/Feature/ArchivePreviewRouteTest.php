<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivePreviewRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_route_serves_supported_files_inline(): void
    {
        Storage::fake('public');

        [$user, $category, $branch] = $this->createScopedUser('01');

        $cases = [
            ['name' => 'Preview PDF', 'path' => 'archives/preview.pdf', 'type' => 'pdf', 'content' => '%PDF-1.4 preview', 'mime' => 'application/pdf'],
            ['name' => 'Preview Image', 'path' => 'archives/preview.jpg', 'type' => 'jpg', 'content' => 'image-bytes', 'mime' => 'image/jpeg'],
            ['name' => 'Preview Video', 'path' => 'archives/preview.mp4', 'type' => 'mp4', 'content' => 'video-bytes', 'mime' => 'video/mp4'],
            ['name' => 'Preview Text', 'path' => 'archives/preview.txt', 'type' => 'txt', 'content' => 'text-preview', 'mime' => 'text/plain'],
        ];

        foreach ($cases as $index => $case) {
            Storage::disk('public')->put($case['path'], $case['content']);

            $archive = Archive::query()->create([
                'archive_category' => $category->id,
                'archive_user' => $user->id,
                'archive_name' => $case['name'],
                'archive_code' => 'PREVIEW-'.$index,
                'archive_description' => 'Archive',
                'archive_path' => $case['path'],
                'archive_type' => $case['type'],
                'archive_branch_office' => $branch->id,
                'archive_date' => '2026-04-15',
            ]);

            $response = $this->actingAs($user)
                ->get(route('archive.preview', ['archive' => $archive->id]));

            $response->assertOk();
            $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
            $this->assertStringStartsWith($case['mime'], (string) $response->headers->get('content-type'));
            $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
        }
    }

    public function test_preview_route_returns_not_found_for_archive_outside_user_scope(): void
    {
        Storage::fake('public');

        [$user, $category] = $this->createScopedUser('01');
        [, , $otherBranch] = $this->createScopedUser('02');

        Storage::disk('public')->put('archives/out-of-scope.pdf', '%PDF-1.4 hidden');

        $archive = Archive::query()->create([
            'archive_category' => $category->id,
            'archive_user' => $user->id,
            'archive_name' => 'Out Scope Preview',
            'archive_code' => 'OOS-PREVIEW',
            'archive_description' => 'Archive',
            'archive_path' => 'archives/out-of-scope.pdf',
            'archive_type' => 'pdf',
            'archive_branch_office' => $otherBranch->id,
            'archive_date' => '2026-04-15',
        ]);

        $this->actingAs($user)
            ->get(route('archive.preview', ['archive' => $archive->id]))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Category, 2: BranchOffice}
     */
    private function createScopedUser(string $branchCode): array
    {
        $category = Category::query()->firstOrCreate([
            'category_name' => 'TABUNGAN',
        ], [
            'category_description' => 'Tabungan',
        ]);

        $branch = BranchOffice::query()->create([
            'branch_code' => $branchCode,
            'branch_name' => 'KC '.$branchCode,
        ]);

        $user = User::factory()->create([
            'branch_office_id' => $branch->id,
        ]);
        $this->assignRole($user, 'kearsipan');
        $user->permittedCategories()->syncWithoutDetaching([$category->id]);

        return [$user, $category, $branch];
    }
}
