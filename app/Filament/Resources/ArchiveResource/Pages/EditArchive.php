<?php

namespace App\Filament\Resources\ArchiveResource\Pages;

use App\Filament\Resources\ArchiveResource;
use App\Models\Archive;
use App\Services\ArchiveBusinessReferenceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditArchive extends EditRecord
{
    protected static string $resource = ArchiveResource::class;

    /**
     * @var array<int|string, mixed>
     */
    protected array $referenceFormData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['business_references'] = app(ArchiveBusinessReferenceService::class)->getFormStateForArchive($this->record);

        return $data;
    }

    protected function beforeValidate(): void
    {
        $file = $this->getUploadedArchiveFile();

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $path = $this->getTargetArchivePath($file);

        if ($path === $this->record->archive_path) {
            return;
        }

        if (! $this->archivePathAlreadyExists($path)) {
            return;
        }

        Notification::make()
            ->title('Update gagal')
            ->body('File sudah pernah diunggah atau sudah ada di arsip. Silakan periksa kembali.')
            ->danger()
            ->send();

        throw ValidationException::withMessages([
            'data.archive_path' => 'File sudah ada di arsip.',
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->referenceFormData = (array) ($data['business_references'] ?? []);
        unset($data['business_references']);

        if (blank($data['archive_path'] ?? null)) {
            $data['archive_path'] = $this->record->archive_path;
            $data['archive_type'] = $this->record->archive_type;
        } else {
            $data['archive_type'] = pathinfo((string) $data['archive_path'], PATHINFO_EXTENSION) ?: $this->record->archive_type;
        }

        return $data;
    }

    protected function afterSave(): void
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ViewAction::make(),
        ];
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
        return Archive::query()->where('archive_path', $path)->whereKeyNot($this->record->getKey())->exists()
            || Storage::disk('public')->exists($path);
    }
}
