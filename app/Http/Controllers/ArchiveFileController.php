<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Services\ArchiveFileService;
use App\Services\ArchiveVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ArchiveFileController extends Controller
{
    public function show(Request $request, Archive $archive, ArchiveFileService $files): Response
    {
        $this->authorizeArchive($request, $archive);

        return $files->response($archive, download: false);
    }

    public function download(Request $request, Archive $archive, ArchiveFileService $files): Response
    {
        $this->authorizeArchive($request, $archive);

        return $files->response($archive, download: true);
    }

    public function bulkDownload(Request $request, ArchiveFileService $files): Response
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:'.ArchiveFileService::BULK_DOWNLOAD_LIMIT],
            'ids.*' => ['integer'],
        ])['ids'];

        return $files->zipResponse(Archive::query()->whereKey($ids)->get(), $request->user());
    }

    private function authorizeArchive(Request $request, Archive $archive): void
    {
        // Checked explicitly (not only via the policy) because super_admin bypasses policies via Gate::before.
        abort_unless(app(ArchiveVisibilityService::class)->canViewArchive($request->user(), $archive), 404);

        Gate::authorize('view', $archive);
    }
}
