<?php

namespace App\Filament\Resources\ArchiveResource\Pages;

use App\Filament\Resources\ArchiveResource;
use App\Models\Archive;
use App\Services\ArchiveBusinessReferenceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateArchive extends CreateRecord
{
    protected static string $resource = ArchiveResource::class;

    /**
     * @var array<int|string, mixed>
     */
    protected array $referenceFormData = [];

    protected function beforeValidate(): void
    {
        $file = $this->getUploadedArchiveFile();

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $path = $this->getTargetArchivePath($file);

        if (! $this->archivePathAlreadyExists($path)) {
            return;
        }

        Notification::make()
            ->title('Upload gagal')
            ->body('File sudah pernah diunggah atau sudah ada di arsip. Silakan periksa kembali.')
            ->danger()
            ->send();

        throw ValidationException::withMessages([
            'data.archive_path' => 'File sudah ada di arsip.',
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['archive_path'] ?? null;

        $this->referenceFormData = (array) ($data['business_references'] ?? []);
        unset($data['business_references']);

        $data['archive_user'] = auth()->id();
        $data['archive_branch_office'] = auth()->user()?->branch_office_id;
        $data['archive_type'] = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'unknown';

        return $data;
    }

    protected function afterCreate(): void
    {
        $service = app(ArchiveBusinessReferenceService::class);
        $service->syncForArchive($this->record, $this->referenceFormData);

        $status = $service->getLinkageStatus($this->record);
        $notification = Notification::make()
            ->title('Status referensi bisnis')
            ->body($status['description']);

        match ($status['tone']) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->send();
    }

    protected function getUploadedArchiveFile(): ?TemporaryUploadedFile
    {
        $state = data_get($this->form->getRawState(), 'archive_path');
        $file = Arr::first(Arr::wrap($state));

        return $file instanceof TemporaryUploadedFile ? $file : null;
    }

    protected function getTargetArchivePath(TemporaryUploadedFile $file): string
    {
        return trim('archives/'.$file->getClientOriginalName(), '/');
    }

    protected function archivePathAlreadyExists(string $path): bool
    {
        return Archive::query()->where('archive_path', $path)->exists()
            || Storage::disk('public')->exists($path);
    }
}
