<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchOfficeResource\Pages;
use App\Models\BranchOffice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BranchOfficeResource extends Resource
{
    protected static ?string $model = BranchOffice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('branch_code')
                    ->label('Kode Cabang')
                    ->maxLength(2)
                    ->required(),
                Forms\Components\TextInput::make('branch_name')
                    ->label('Nama')
                    ->required(),
                Forms\Components\Textarea::make('branch_description')
                    ->label('Deskripsi')
                    ->maxLength(500)
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch_code')
                    ->label('Kode Cabang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch_name')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch_description')
                    ->label('Deskripsi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchOffices::route('/'),
            'create' => Pages\CreateBranchOffice::route('/create'),
            'edit' => Pages\EditBranchOffice::route('/{record}/edit'),
        ];
    }
}
