<?php

namespace App\Filament\Resources\BranchOfficeResource\Pages;

use App\Filament\Resources\BranchOfficeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchOffice extends EditRecord
{
    protected static string $resource = BranchOfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
