<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArchiveResource\Pages;
use App\Models\Archive;
use App\Models\Category;
use App\Services\ArchiveBusinessReferenceService;
use App\Support\ReferenceNormalizer;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Section as InfolistSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ArchiveResource extends Resource
{
    protected static ?string $model = Archive::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('archive_name')
                    ->label('Nama Arsip')
                    ->columnSpanFull()
                    ->required(),
                Forms\Components\TextInput::make('archive_code')
                    ->label('Kode Arsip')
                    ->columnSpanFull()
                    ->hint(new HtmlString('<span class="text-sm text-gray-500">Kosongkan jika tidak diperlukan</span>')),
                Forms\Components\Select::make('archive_category')
                    ->label('Kategori Arsip')
                    ->options(fn (): array => static::getCategoryOptions())
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('business_references', []);
                    })
                    ->preload()
                    ->searchable()
                    ->columnSpanFull()
                    ->required(),
                Forms\Components\DatePicker::make('archive_date')
                    ->label('Tanggal Arsip')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('archive_description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->rows(5)
                    ->maxLength(255)
                    ->required(),
                FormSection::make('Referensi Bisnis')
                    ->schema(fn (Get $get): array => static::buildBusinessReferenceSchema((int) ($get('archive_category') ?: 0)))
                    ->hidden(fn (Get $get): bool => static::businessReferenceFieldDefinitions((int) ($get('archive_category') ?: 0))->isEmpty())
                    ->columnSpanFull()
                    ->compact(),
                Forms\Components\FileUpload::make('archive_path')
                    ->label('File Arsip')
                    ->disk('public')
                    ->directory('archives')
                    ->preserveFilenames()
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'text/plain',
                        'video/mp4',
                        'video/x-msvideo',
                        'video/x-matroska',
                        'video/webm',
                    ])
                    ->maxSize(50 * 1024)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull()
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.category_name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branchOffice.branch_name')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('archive_code')
                    ->label('Kode Arsip')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('archive_name')
                    ->label('Nama Arsip')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('primary_reference')
                    ->label('Referensi Utama')
                    ->state(fn (Archive $record): string => static::primaryReferenceValue($record))
                    ->searchable(false),
                Tables\Columns\TextColumn::make('archive_date')
                    ->label('Tanggal Arsip')
                    ->formatStateUsing(fn ($state): string => static::formatDateValue($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Upload')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('archive_category')
                    ->label('Kategori Arsip')
                    ->options(fn (): array => static::getCategoryOptions())
                    ->searchable()
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('archive_branch_office')
                    ->label('Cabang')
                    ->relationship('branchOffice', 'branch_name')
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                Tables\Filters\Filter::make('reference_value')
                    ->label('Referensi Bisnis')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Cari Referensi'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = trim((string) ($data['value'] ?? ''));

                        if ($value === '') {
                            return $query;
                        }

                        $normalized = ReferenceNormalizer::normalize($value);

                        return $query->whereHas('businessReferences', function (Builder $referenceQuery) use ($normalized): void {
                            $referenceQuery->where('normalized_value', $normalized);
                        });
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Archive $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema([
                        InfolistSection::make('Informasi Arsip')
                            ->schema([
                                TextEntry::make('archive_name')->label('Nama Arsip'),
                                TextEntry::make('archive_code')->label('Kode Arsip')->placeholder('-'),
                                TextEntry::make('category.category_name')->label('Kategori Arsip'),
                                TextEntry::make('branchOffice.branch_name')->label('Cabang')->placeholder('-'),
                                TextEntry::make('archive_date')
                                    ->label('Tanggal Arsip')
                                    ->formatStateUsing(fn ($state): string => static::formatDateValue($state)),
                                TextEntry::make('archive_description')->label('Deskripsi'),
                                TextEntry::make('business_references_html')
                                    ->label('Referensi Bisnis')
                                    ->state(fn (Archive $record): HtmlString => static::renderBusinessReferenceHtml($record))
                                    ->html(),
                            ]),
                        InfolistSection::make('File Arsip')
                            ->schema([
                                TextEntry::make('archive_preview')
                                    ->label('Preview')
                                    ->state(fn (Archive $record): HtmlString => static::renderArchivePreviewHtml($record))
                                    ->html(),
                            ]),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'category',
                'user',
                'branchOffice',
                'businessReferences.categoryReferenceField',
            ]);

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->hasRole(['staff', 'it'])) {
            return $query->whereIn('archive_category', $user->permittedCategories()->pluck('categories.id'));
        }

        return $query
            ->whereIn('archive_category', $user->permittedCategories()->pluck('categories.id'))
            ->where('archive_branch_office', $user->branch_office_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArchives::route('/'),
            'create' => Pages\CreateArchive::route('/create'),
            'edit' => Pages\EditArchive::route('/{record}/edit'),
            'view' => Pages\ViewArchive::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count >= 1000 ? number_format($count / 1000, 1).'k' : number_format($count, 0, ',', '.');
    }

    /**
     * @return Collection<int, \App\Models\CategoryReferenceField>
     */
    public static function businessReferenceFieldDefinitions(?int $categoryId): Collection
    {
        return app(ArchiveBusinessReferenceService::class)->getFieldDefinitionsForCategory($categoryId);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function buildBusinessReferenceSchema(?int $categoryId): array
    {
        return static::businessReferenceFieldDefinitions($categoryId)
            ->map(function ($field) {
                return Forms\Components\TextInput::make("business_references.{$field->id}")
                    ->label($field->label)
                    ->helperText($field->help_text)
                    ->required((bool) $field->is_required)
                    ->maxLength(255)
                    ->columnSpanFull();
            })
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public static function getCategoryOptions(): array
    {
        $user = auth()->user();

        if (! $user || $user->isAdmin()) {
            return Category::query()->orderBy('category_name')->pluck('category_name', 'id')->all();
        }

        return $user->permittedCategories()->orderBy('category_name')->pluck('category_name', 'categories.id')->all();
    }

    public static function formatDateValue(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)->translatedFormat('d F Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private static function primaryReferenceValue(Archive $record): string
    {
        $record->loadMissing('businessReferences.categoryReferenceField');

        $primaryReference = $record->businessReferences
            ->first(fn ($reference) => (bool) $reference->categoryReferenceField?->is_primary_match_key);

        return $primaryReference?->raw_value ?: '-';
    }

    private static function renderBusinessReferenceHtml(Archive $record): HtmlString
    {
        $record->loadMissing('businessReferences.categoryReferenceField');

        if ($record->businessReferences->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">Belum ada referensi bisnis.</span>');
        }

        $items = $record->businessReferences
            ->sortBy(fn ($reference) => $reference->categoryReferenceField?->sort_order ?? 999)
            ->map(function ($reference): string {
                $label = e($reference->categoryReferenceField?->label ?? $reference->reference_type);
                $value = e($reference->raw_value);

                return "<li><strong>{$label}:</strong> {$value}</li>";
            })
            ->implode('');

        return new HtmlString("<ul class=\"list-disc pl-4\">{$items}</ul>");
    }

    private static function renderArchivePreviewHtml(Archive $record): HtmlString
    {
        if (! $record->archive_path) {
            return new HtmlString('<span class="text-sm text-gray-500">File arsip tidak tersedia.</span>');
        }

        $url = e(Storage::disk('public')->url($record->archive_path));

        if (strtolower((string) $record->archive_type) === 'pdf') {
            return new HtmlString(
                <<<HTML
                <div class="space-y-3">
                    <iframe src="{$url}" class="w-full rounded-lg border" style="min-height: 70vh;"></iframe>
                    <a href="{$url}" target="_blank" class="text-primary-600 underline">Download Arsip</a>
                </div>
                HTML,
            );
        }

        return new HtmlString("<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 underline\">Download Arsip</a>");
    }
}
