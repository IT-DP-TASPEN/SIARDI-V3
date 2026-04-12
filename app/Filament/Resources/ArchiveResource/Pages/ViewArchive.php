<?php

namespace App\Filament\Resources\ArchiveResource\Pages;

use App\Filament\Resources\ArchiveResource;
use App\Jobs\RematchArchiveReferencesJob;
use App\Services\ArchiveBusinessReferenceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewArchive extends ViewRecord
{
    protected static string $resource = ArchiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rematchReferences')
                ->label('Rematch Referensi')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => app(ArchiveBusinessReferenceService::class)->visibleBusinessReferences($this->record)->isNotEmpty())
                ->action(function (): void {
                    RematchArchiveReferencesJob::dispatch((int) $this->record->getKey());

                    Notification::make()
                        ->title('Job rematch dikirim')
                        ->body('Referensi arsip akan dicocokkan ulang di background.')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
