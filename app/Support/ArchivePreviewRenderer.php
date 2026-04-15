<?php

namespace App\Support;

use App\Models\Archive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ArchivePreviewRenderer
{
    private const TEXT_PREVIEW_MAX_BYTES = 262144;

    public static function render(Archive $archive, string $height = '70vh'): HtmlString
    {
        if (! filled($archive->archive_path)) {
            return new HtmlString('<span class="text-sm text-gray-500">File arsip tidak tersedia.</span>');
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($archive->archive_path)) {
            return new HtmlString('<span class="text-sm text-gray-500">File arsip tidak tersedia.</span>');
        }

        $url = e($disk->url($archive->archive_path));
        $extension = static::extension($archive);

        if ($extension === 'pdf') {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <iframe src="{$url}" class="w-full rounded-lg border" style="min-height: {$height};"></iframe>
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <img src="{$url}" alt="Preview Arsip" class="w-full rounded-lg border object-contain" style="max-height: {$height};">
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['mp4', 'webm', 'avi', 'mkv'], true)) {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <video controls class="w-full rounded-lg border" style="max-height: {$height};">
                        <source src="{$url}">
                    </video>
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['txt', 'text'], true)) {
            return static::renderTextPreview($archive->archive_path, $url, $height);
        }

        return new HtmlString("<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-primary-600 underline\">Buka / Download Arsip</a>");
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
}
