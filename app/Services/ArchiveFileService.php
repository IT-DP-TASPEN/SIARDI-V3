<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

/**
 * Owns the physical lifecycle of archive files: storing, serving, replacing,
 * deleting and migrating legacy public files to the private archive disk.
 */
class ArchiveFileService
{
    public const DISK = 'archives';

    public const LEGACY_DISK = 'public';

    public const BULK_DOWNLOAD_LIMIT = 100;

    /**
     * Types a browser may render inline. Everything else is served as an attachment.
     */
    private const INLINE_MIME_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        'txt' => 'text/plain; charset=UTF-8',
        'text' => 'text/plain; charset=UTF-8',
    ];

    public function storageName(TemporaryUploadedFile $file): string
    {
        return $this->generateName((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
    }

    /**
     * Store a validated upload on the private disk and return its path. Failures are
     * logged with an error code and surfaced as a safe validation message.
     */
    public function storeUpload(TemporaryUploadedFile $file, string $operation, string $errorKey, ?Archive $archive = null): string
    {
        try {
            if (! $file->exists()) {
                throw new RuntimeException('Temporary upload is missing or expired.');
            }

            return $file->storeAs('', $this->storageName($file), self::DISK)
                ?: throw new RuntimeException('Archive disk did not return a stored path.');
        } catch (Throwable $exception) {
            $code = $this->logFailure($operation, $exception, $archive, [
                'original_name' => $file->getClientOriginalName(),
                'size' => rescue(fn () => $file->getSize(), report: false),
                'mime_type' => rescue(fn () => $file->getMimeType(), report: false),
            ]);

            throw ValidationException::withMessages([
                $errorKey => "File tidak dapat disimpan. Silakan coba kembali atau hubungi IT dengan kode {$code}.",
            ]);
        }
    }

    public function diskName(Archive $archive): string
    {
        return $archive->archive_disk === self::LEGACY_DISK ? self::LEGACY_DISK : self::DISK;
    }

    public function disk(Archive $archive): Filesystem
    {
        return Storage::disk($this->diskName($archive));
    }

    public function exists(Archive $archive, string $operation = 'preview'): bool
    {
        if (blank($archive->archive_path)) {
            return false;
        }

        try {
            return $this->disk($archive)->exists($archive->archive_path);
        } catch (Throwable $exception) {
            $this->logFailure($operation, $exception, $archive);

            return false;
        }
    }

    public function displayName(Archive $archive): string
    {
        return $archive->archive_original_name ?: basename((string) $archive->archive_path);
    }

    public function extension(Archive $archive): string
    {
        $extension = ltrim(strtolower((string) $archive->archive_type), '.');

        return $extension !== '' ? $extension : strtolower(pathinfo((string) $archive->archive_path, PATHINFO_EXTENSION));
    }

    public function inlineMimeType(Archive $archive): ?string
    {
        return self::INLINE_MIME_TYPES[$this->extension($archive)] ?? null;
    }

    /**
     * Stream the file to an already-authorized user.
     */
    public function response(Archive $archive, bool $download): Response
    {
        $operation = $download ? 'download' : 'preview';

        if (! $this->exists($archive, $operation)) {
            $code = $this->logFailure($operation, 'Archive file is missing from storage.', $archive, level: 'warning');

            return response("File arsip tidak ditemukan. Sampaikan kode {$code} kepada IT.", 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $mimeType = $this->inlineMimeType($archive);
        $inline = ! $download && $mimeType !== null;

        // ponytail: local-driver only (BinaryFileResponse gives Range support for video); use disk->response() for S3.
        $response = response()->download(
            $this->disk($archive)->path($archive->archive_path),
            $this->displayName($archive),
            [
                'Content-Type' => $inline ? $mimeType : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $inline ? 'inline' : 'attachment',
        );

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * Zip the archives the user may view; unreadable or missing ones are listed in _missing.txt.
     *
     * @param  Collection<int, Archive>  $archives
     */
    public function zipResponse(Collection $archives, User $user): BinaryFileResponse
    {
        $visibility = app(ArchiveVisibilityService::class);
        $zipPath = tempnam(sys_get_temp_dir(), 'siardi-zip-');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create temporary zip file.');
        }

        $used = ['_missing.txt' => true];
        $missing = [];
        $added = [];

        // Invisible archives are dropped silently, like the single-file 404, so ids can't be probed.
        $archives = $archives->filter(fn (Archive $archive): bool => $visibility->canViewArchive($user, $archive)
            && Gate::forUser($user)->allows('view', $archive));

        foreach ($archives as $archive) {
            if (! $this->exists($archive, 'bulk_download')) {
                $missing[] = "#{$archive->getKey()} {$archive->archive_name} (file tidak ditemukan)";

                continue;
            }

            $zip->addFile($this->disk($archive)->path($archive->archive_path), $this->uniqueName($this->displayName($archive), $used));
            $added[] = $archive->getKey();
        }

        if ($missing !== []) {
            $zip->addFromString('_missing.txt', implode("\n", $missing)."\n");
        }

        // An empty ZipArchive is not written on close(); keep the response a valid (empty) zip.
        if ($added === [] && $missing === []) {
            $zip->addFromString('_missing.txt', "Tidak ada arsip dipilih.\n");
        }

        $zip->close();

        Log::info('Archive bulk download', [
            'user_id' => $user->getKey(),
            'archive_ids' => $added,
            'skipped' => count($missing),
        ]);

        $response = response()
            ->download($zipPath, 'arsip-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueName(string $name, array &$used): string
    {
        $name = str_replace(['/', '\\'], '_', $name);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;

        for ($i = 2; isset($used[strtolower($candidate)]); $i++) {
            $candidate = "{$base} ({$i})".($ext !== '' ? ".{$ext}" : '');
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * Delete the record first, then its file. A failed DB delete keeps the file; a failed
     * file delete is logged for IT instead of failing an already-committed deletion.
     */
    public function deleteArchive(Archive $archive): bool
    {
        $disk = $this->diskName($archive);
        $path = $archive->archive_path;

        if (! $archive->delete()) {
            return false;
        }

        $this->deleteFile($disk, $path, 'delete', $archive);

        return true;
    }

    /**
     * Best-effort physical delete. Never throws; failures are logged.
     */
    public function deleteFile(string $disk, ?string $path, string $operation, ?Archive $archive = null): bool
    {
        if (blank($path)) {
            return true;
        }

        try {
            if (Storage::disk($disk)->delete($path)) {
                return true;
            }

            $error = 'Filesystem delete returned false.';
        } catch (Throwable $exception) {
            $error = $exception;
        }

        $this->logFailure($operation, $error, $archive, ['disk' => $disk, 'path' => $path], 'warning');

        return false;
    }

    /**
     * Move one legacy public file to the private disk. Safe to rerun: only rows still on
     * the legacy disk are touched, and the DB is updated only after a verified copy.
     *
     * @return 'skipped'|'missing'|'pending'|'migrated'|'failed'
     */
    public function migrateLegacyFile(Archive $archive, bool $dryRun = false): string
    {
        if ($this->diskName($archive) !== self::LEGACY_DISK) {
            return 'skipped';
        }

        $legacyPath = (string) $archive->archive_path;
        $source = Storage::disk(self::LEGACY_DISK);

        if ($legacyPath === '' || ! $source->exists($legacyPath)) {
            if (! $dryRun) {
                $this->logFailure('migrate', 'Legacy archive file is missing.', $archive, ['path' => $legacyPath], 'warning');
            }

            return 'missing';
        }

        if ($dryRun) {
            return 'pending';
        }

        $target = Storage::disk(self::DISK);
        $newPath = $this->generateName(pathinfo($legacyPath, PATHINFO_EXTENSION));

        try {
            $stream = $source->readStream($legacyPath) ?: throw new RuntimeException('Unable to read legacy file.');

            try {
                $target->writeStream($newPath, $stream);
            } finally {
                is_resource($stream) && fclose($stream);
            }

            if ($target->size($newPath) !== $source->size($legacyPath)) {
                throw new RuntimeException('Copied archive file size does not match the legacy file.');
            }

            $archive->forceFill([
                'archive_path' => $newPath,
                'archive_disk' => self::DISK,
                'archive_original_name' => $archive->archive_original_name ?: basename($legacyPath),
            ])->save();
        } catch (Throwable $exception) {
            $archive->setRawAttributes($archive->getRawOriginal(), true);
            $this->deleteFile(self::DISK, $newPath, 'migrate', $archive);
            $this->logFailure('migrate', $exception, $archive, ['path' => $legacyPath]);

            return 'failed';
        }

        $this->deleteFile(self::LEGACY_DISK, $legacyPath, 'migrate', $archive);

        return 'migrated';
    }

    /**
     * Log a storage lifecycle failure server-side and return a short code safe to show users.
     *
     * @param  array<string, mixed>  $context
     */
    public function logFailure(string $operation, Throwable|string $error, ?Archive $archive = null, array $context = [], string $level = 'error'): string
    {
        $code = (in_array($operation, ['create', 'replace'], true) ? 'UPL-' : 'ARC-').Str::upper(Str::random(8));
        $user = auth()->user();

        Log::log($level, "Archive file {$operation} failed.", [
            'error_code' => $code,
            'operation' => $operation,
            'user_id' => $user?->getAuthIdentifier(),
            'branch_id' => $user?->branch_office_id,
            'archive_id' => $archive?->getKey(),
            'original_name' => $archive?->archive_original_name,
            ...$context,
            'exception_class' => $error instanceof Throwable ? $error::class : null,
            'exception_message' => $error instanceof Throwable ? $error->getMessage() : $error,
        ]);

        return $code;
    }

    private function generateName(string $extension): string
    {
        $extension = strtolower($extension);

        if (! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            $extension = 'bin';
        }

        return strtolower((string) Str::ulid()).'.'.$extension;
    }
}
