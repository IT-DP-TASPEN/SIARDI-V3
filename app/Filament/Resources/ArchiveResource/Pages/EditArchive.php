<?php

namespace App\Filament\Resources\ArchiveResource\Pages;

use App\Filament\Resources\ArchiveResource;
use App\Models\Archive;
use App\Services\ArchiveBusinessReferenceService;
use App\Services\ArchiveFileService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->referenceFormData = (array) ($data['business_references'] ?? []);
        unset($data['business_references']);

        // Removing the file without uploading a replacement keeps the current file.
        if (blank($data['archive_path'] ?? null)) {
            $data['archive_path'] = $this->record->archive_path;
            $data['archive_original_name'] = $this->record->archive_original_name;
            $data['archive_type'] = $this->record->archive_type;

            return $data;
        }

        $data['archive_type'] = pathinfo((string) $data['archive_path'], PATHINFO_EXTENSION) ?: $this->record->archive_type;

        if ($data['archive_path'] !== $this->record->archive_path) {
            $data['archive_disk'] = ArchiveFileService::DISK;
        }

        return $data;
    }

    /**
     * Replacement order: new file stored (during form dehydration) -> record persisted ->
     * previous file deleted. On persistence failure the new file is removed and the
     * previous file is left untouched.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $files = app(ArchiveFileService::class);
        $previousDisk = $files->diskName($record);
        $previousPath = $record->archive_path;
        $replaced = $data['archive_path'] !== $previousPath;

        try {
            $record = parent::handleRecordUpdate($record, $data);
        } catch (Throwable $exception) {
            $record->setRawAttributes($record->getRawOriginal(), true);

            if ($replaced) {
                $files->deleteFile(ArchiveFileService::DISK, $data['archive_path'], 'replace', $record);
            }

            $code = $files->logFailure($replaced ? 'replace' : 'update', $exception, $record, [
                'original_name' => $data['archive_original_name'] ?? null,
            ]);

            $this->data['archive_path'] = [(string) Str::uuid() => $previousPath];
            $this->data['archive_original_name'] = $record->archive_original_name;

            Notification::make()
                ->title('Perubahan arsip gagal disimpan')
                ->body("File arsip sebelumnya tetap digunakan. Silakan coba kembali. Jika masalah berlanjut, sampaikan kode {$code} kepada IT.")
                ->danger()
                ->persistent()
                ->send();

            $this->halt(true);
        }

        if ($replaced) {
            $files->deleteFile($previousDisk, $previousPath, 'replace', $record);
        }

        return $record;
    }

    protected function afterSave(): void
    {
        ArchiveResource::syncBusinessReferences($this->record, $this->referenceFormData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(fn (Archive $record): bool => app(ArchiveFileService::class)->deleteArchive($record)),
            Actions\ViewAction::make(),
        ];
    }
}
