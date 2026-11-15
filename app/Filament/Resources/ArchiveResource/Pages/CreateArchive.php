<?php

namespace App\Filament\Resources\ArchiveResource\Pages;

use App\Filament\Resources\ArchiveResource;
use App\Services\ArchiveFileService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateArchive extends CreateRecord
{
    protected static string $resource = ArchiveResource::class;

    /**
     * @var array<int|string, mixed>
     */
    protected array $referenceFormData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['archive_path'] ?? null;

        $this->referenceFormData = (array) ($data['business_references'] ?? []);
        unset($data['business_references']);

        $data['archive_user'] = auth()->id();
        $data['archive_branch_office'] = auth()->user()?->branch_office_id;
        $data['archive_disk'] = ArchiveFileService::DISK;
        $data['archive_type'] = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'unknown';

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (Throwable $exception) {
            // The file was stored during form dehydration; without a record it would be unmanaged.
            $files = app(ArchiveFileService::class);
            $files->deleteFile(ArchiveFileService::DISK, $data['archive_path'] ?? null, 'create');
            $code = $files->logFailure('create', $exception, context: [
                'original_name' => $data['archive_original_name'] ?? null,
            ]);

            $this->data['archive_path'] = [];
            $this->data['archive_original_name'] = null;

            Notification::make()
                ->title('Arsip gagal disimpan')
                ->body("Arsip dan file tidak tersimpan. Silakan unggah ulang file lalu coba kembali. Jika masalah berlanjut, sampaikan kode {$code} kepada IT.")
                ->danger()
                ->persistent()
                ->send();

            $this->halt(true);
        }
    }

    protected function afterCreate(): void
    {
        ArchiveResource::syncBusinessReferences($this->record, $this->referenceFormData);
    }
}
