<?php

namespace App\Support;

use App\Models\Archive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ArchivePreviewRenderer
{
    private const TEXT_PREVIEW_MAX_BYTES = 262144;

    /**
     * @var array<string, string>
     */
    private const INLINE_PREVIEW_MIME_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'txt' => 'text/plain',
        'text' => 'text/plain',
    ];

    public static function render(Archive $archive, string $height = '70vh'): HtmlString
    {
        if (! filled($archive->archive_path)) {
            return new HtmlString('<span class="text-sm text-gray-500">File arsip tidak tersedia.</span>');
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($archive->archive_path)) {
            return new HtmlString('<span class="text-sm text-gray-500">File arsip tidak tersedia.</span>');
        }

        $previewUrl = e(static::previewUrl($archive));
        $publicUrl = e($disk->url($archive->archive_path));
        $extension = static::extension($archive);

        if ($extension === 'pdf') {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <iframe src="{$previewUrl}" class="w-full rounded-lg border" style="min-height: {$height};"></iframe>
                    <a href="{$previewUrl}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <img src="{$previewUrl}" alt="Preview Arsip" class="w-full rounded-lg border object-contain" style="max-height: {$height};">
                    <a href="{$previewUrl}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['mp4', 'webm', 'avi', 'mkv'], true)) {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <video controls class="w-full rounded-lg border" style="max-height: {$height};">
                        <source src="{$previewUrl}">
                    </video>
                    <a href="{$previewUrl}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['txt', 'text'], true)) {
            return static::renderTextPreview($archive->archive_path, $previewUrl, $height);
        }

        return new HtmlString("<a href=\"{$publicUrl}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-primary-600 underline\">Buka / Download Arsip</a>");
    }

    public static function isInlinePreviewable(Archive $archive): bool
    {
        return static::previewMimeType($archive) !== null;
    }

    public static function previewMimeType(Archive $archive): ?string
    {
        return self::INLINE_PREVIEW_MIME_TYPES[static::extension($archive)] ?? null;
    }

    private static function renderTextPreview(string $path, string $url, string $height): HtmlString
    {
        $disk = Storage::disk('public');

        try {
            if ($disk->size($path) > self::TEXT_PREVIEW_MAX_BYTES) {
                return new HtmlString(
                    "<div class=\"space-y-3\"><p class=\"text-sm text-gray-500\">Preview teks dibatasi karena file terlalu besar.</p><a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-primary-600 underline\">Buka / Download Arsip</a></div>",
                );
            }

            $content = e($disk->get($path));

            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <pre class="overflow-auto rounded-lg border bg-gray-50 p-4 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-200" style="max-height: {$height}; white-space: pre-wrap;">{$content}</pre>
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        } catch (\Throwable) {
            return new HtmlString("<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-primary-600 underline\">Buka / Download Arsip</a>");
        }
    }

    private static function extension(Archive $archive): string
    {
        $extension = strtolower((string) $archive->archive_type);

        if ($extension !== '') {
            return ltrim($extension, '.');
        }

        return strtolower((string) pathinfo((string) $archive->archive_path, PATHINFO_EXTENSION));
    }

    private static function previewUrl(Archive $archive): string
    {
        return route('archive.preview', ['archive' => $archive->getKey()]);
    }
}
