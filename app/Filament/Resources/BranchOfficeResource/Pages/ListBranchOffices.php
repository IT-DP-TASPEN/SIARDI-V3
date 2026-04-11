<?php

namespace App\Filament\Resources\BranchOfficeResource\Pages;

use App\Filament\Resources\BranchOfficeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranchOffices extends ListRecords
{
    protected static string $resource = BranchOfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
