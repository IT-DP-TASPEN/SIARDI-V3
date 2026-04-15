<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Services\ArchiveVisibilityService;
use App\Support\ArchivePreviewRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ArchivePreviewController extends Controller
{
    public function show(int $archive, ArchiveVisibilityService $visibility)
    {
        $record = $visibility->applyArchiveScope(
            Archive::query(),
            auth()->user(),
        )->findOrFail($archive);

        Gate::authorize('view', $record);

        abort_unless(ArchivePreviewRenderer::isInlinePreviewable($record), 404);

        abort_unless(filled($record->archive_path), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($record->archive_path), 404);

        $mimeType = ArchivePreviewRenderer::previewMimeType($record)
            ?? ($disk->mimeType($record->archive_path) ?: 'application/octet-stream');

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                basename((string) $record->archive_path),
            ),
        ];

        if ($mimeType !== 'application/octet-stream') {
            $headers['X-Content-Type-Options'] = 'nosniff';
        }

        return response()->file($disk->path($record->archive_path), $headers);
    }
}
