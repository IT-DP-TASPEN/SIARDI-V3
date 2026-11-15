<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // archives.archive_category is RESTRICT: historical archives must not be cascaded away.
            Actions\DeleteAction::make()
                ->hidden(fn (Category $record): bool => $record->archives()->exists()),
        ];
    }
}
