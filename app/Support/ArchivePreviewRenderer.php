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
            return static::renderPdfPreview($url, e(basename((string) $archive->archive_path)), $height);
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return new HtmlString(
                <<<HTML
                <div class="siardi-preview-stack">
                    <img src="{$url}" alt="Preview Arsip" class="siardi-preview-image" style="max-height: {$height};">
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="siardi-preview-link">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['mp4', 'webm', 'avi', 'mkv'], true)) {
            return new HtmlString(
                <<<HTML
                <div class="siardi-preview-stack">
                    <video controls class="siardi-preview-video" style="max-height: {$height};">
                        <source src="{$url}">
                    </video>
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="siardi-preview-link">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        }

        if (in_array($extension, ['txt', 'text'], true)) {
            return static::renderTextPreview($archive->archive_path, $url, $height);
        }

        return new HtmlString("<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"siardi-preview-link\">Buka / Download Arsip</a>");
    }

    private static function renderTextPreview(string $path, string $url, string $height): HtmlString
    {
        $disk = Storage::disk('public');

        try {
            if ($disk->size($path) > self::TEXT_PREVIEW_MAX_BYTES) {
                return new HtmlString(
                    "<div class=\"siardi-preview-stack\"><p class=\"text-sm text-gray-500\">Preview teks dibatasi karena file terlalu besar.</p><a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"siardi-preview-link\">Buka / Download Arsip</a></div>",
                );
            }

            $content = e($disk->get($path));

            return new HtmlString(
                <<<HTML
                <div class="siardi-preview-stack">
                    <pre class="siardi-preview-text" style="max-height: {$height}; white-space: pre-wrap;">{$content}</pre>
                    <a href="{$url}" target="_blank" rel="noopener noreferrer" class="siardi-preview-link">Buka / Download Arsip</a>
                </div>
                HTML,
            );
        } catch (\Throwable) {
            return new HtmlString("<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"siardi-preview-link\">Buka / Download Arsip</a>");
        }
    }

    private static function renderPdfPreview(string $url, string $filename, string $height): HtmlString
    {
        return new HtmlString(
            <<<HTML
            <div
                class="siardi-pdf-preview"
                data-siardi-pdf-preview
                data-src="{$url}"
                data-filename="{$filename}"
                data-height="{$height}"
            >
                <div class="siardi-pdf-preview__toolbar" role="toolbar" aria-label="Kontrol preview PDF">
                    <div class="siardi-pdf-preview__control-group">
                        <button type="button" class="siardi-pdf-preview__button" data-action="prev">Sebelumnya</button>
                        <span class="siardi-pdf-preview__meta" data-role="page-indicator">Halaman 1 / --</span>
                        <button type="button" class="siardi-pdf-preview__button" data-action="next">Berikutnya</button>
                    </div>
                    <div class="siardi-pdf-preview__control-group">
                        <button type="button" class="siardi-pdf-preview__button" data-action="zoom-out">-</button>
                        <span class="siardi-pdf-preview__meta" data-role="zoom-indicator">100%</span>
                        <button type="button" class="siardi-pdf-preview__button" data-action="zoom-in">+</button>
                        <button type="button" class="siardi-pdf-preview__button" data-action="fit-width">Fit Width</button>
                    </div>
                </div>

                <div class="siardi-pdf-preview__viewport" data-role="viewport" style="min-height: {$height};">
                    <div class="siardi-pdf-preview__state" data-role="loading">Memuat preview PDF...</div>
                    <div class="siardi-pdf-preview__state siardi-pdf-preview__state--error" data-role="error" hidden>
                        Preview PDF gagal dimuat. Gunakan link di bawah untuk membuka file.
                    </div>
                    <canvas class="siardi-pdf-preview__canvas" data-role="canvas" hidden></canvas>
                </div>

                <a href="{$url}" target="_blank" rel="noopener noreferrer" class="siardi-preview-link">Buka / Download Arsip</a>
            </div>
            HTML,
        );
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
