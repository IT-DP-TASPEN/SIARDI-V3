<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArchiveResource\Pages;
use App\Jobs\RematchArchiveReferencesJob;
use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Services\ArchiveBusinessReferenceService;
use App\Services\ArchiveVisibilityService;
use App\Support\ArchivePreviewRenderer;
use App\Support\ReferenceNormalizer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
                Forms\Components\Placeholder::make('business_reference_configuration_notice')
                    ->label('Status Referensi Bisnis')
                    ->content(fn (Get $get): HtmlString => static::businessReferenceConfigurationNotice((int) ($get('archive_category') ?: 0)) ?? new HtmlString(''))
                    ->hidden(fn (Get $get): bool => static::businessReferenceConfigurationNotice((int) ($get('archive_category') ?: 0)) === null)
                    ->columnSpanFull(),
                FormSection::make('Referensi Bisnis')
                    ->description(fn (Get $get): string => static::businessReferenceSectionDescription((int) ($get('archive_category') ?: 0)))
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
                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->state(fn (Archive $record): string => static::businessReferenceValue($record, 'cif'))
                    ->searchable(
                        query: fn (Builder $query, string $search): Builder => static::applyBusinessReferenceSearch($query, 'cif', $search),
                    ),
                Tables\Columns\TextColumn::make('primary_reference')
                    ->label('Referensi Utama')
                    ->state(fn (Archive $record): string => static::primaryReferenceValue($record))
                    ->searchable(false),
                Tables\Columns\TextColumn::make('archive_description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('linkage_status')
                    ->label('Status Linkage')
                    ->state(fn (Archive $record): string => static::archiveLinkageStatus($record)['label'])
                    ->badge()
                    ->color(fn (Archive $record): string => static::archiveLinkageStatus($record)['color']),
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
                    ->options(fn (): array => static::getBranchOptions())
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->visible(fn (): bool => app(ArchiveVisibilityService::class)->canViewAllBranches(auth()->user())),
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
                        $deprecatedReferenceTypes = app(ArchiveBusinessReferenceService::class)->deprecatedReferenceTypes();

                        return $query->whereHas('businessReferences', function (Builder $referenceQuery) use ($deprecatedReferenceTypes, $normalized): void {
                            $referenceQuery
                                ->where('normalized_value', $normalized)
                                ->whereNotIn('reference_type', $deprecatedReferenceTypes);
                        });
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('rematchReferences')
                    ->label('Rematch Referensi')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Archive $record): bool => app(ArchiveBusinessReferenceService::class)->visibleBusinessReferences($record)->isNotEmpty())
                    ->action(function (Archive $record): void {
                        RematchArchiveReferencesJob::dispatch($record->getKey());

                        Notification::make()
                            ->title('Job rematch dikirim')
                            ->body('Referensi arsip akan dicocokkan ulang di background.')
                            ->success()
                            ->send();
                    }),
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
                Grid::make(2)
                    ->columnSpanFull()
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
                                TextEntry::make('linkage_status')
                                    ->label('Status Linkage')
                                    ->state(fn (Archive $record): string => static::archiveLinkageStatus($record)['label'])
                                    ->badge()
                                    ->color(fn (Archive $record): string => static::archiveLinkageStatus($record)['color']),
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
                'legacyInactiveMarker',
            ]);

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        return app(ArchiveVisibilityService::class)->applyArchiveScope($query, $user);
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
     * @return Collection<int, CategoryReferenceField>
     */
    public static function businessReferenceFieldDefinitions(?int $categoryId): Collection
    {
        return app(ArchiveBusinessReferenceService::class)->getFieldDefinitionsForCategory($categoryId);
    }

    /**
     * @return array<int, Component>
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
        $visibleCategoryIds = app(ArchiveVisibilityService::class)->visibleCategoryIds($user);

        if ($visibleCategoryIds === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $visibleCategoryIds)
            ->orderBy('category_name')
            ->pluck('category_name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public static function getBranchOptions(): array
    {
        $user = auth()->user();
        $visibleBranchOfficeIds = app(ArchiveVisibilityService::class)->visibleBranchOfficeIds($user);

        if ($visibleBranchOfficeIds === []) {
            return [];
        }

        return BranchOffice::query()
            ->whereIn('id', $visibleBranchOfficeIds)
            ->orderBy('branch_code')
            ->get()
            ->mapWithKeys(fn (BranchOffice $branch): array => [
                $branch->id => trim($branch->branch_code.' - '.$branch->branch_name),
            ])
            ->all();
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

    public static function archiveLinkageStatus(Archive $record): array
    {
        return app(ArchiveBusinessReferenceService::class)->getLinkageStatus($record);
    }

    private static function primaryReferenceValue(Archive $record): string
    {
        $record->loadMissing('businessReferences.categoryReferenceField');

        $primaryReference = $record->businessReferences
            ->first(fn ($reference) => (bool) $reference->categoryReferenceField?->is_primary_match_key);

        return $primaryReference?->raw_value ?: '-';
    }

    private static function businessReferenceValue(Archive $record, string $referenceType): string
    {
        $record->loadMissing('businessReferences');

        $reference = $record->businessReferences
            ->firstWhere('reference_type', $referenceType);

        return $reference?->raw_value ?: ($reference?->normalized_value ?: '-');
    }

    private static function applyBusinessReferenceSearch(Builder $query, string $referenceType, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $normalized = ReferenceNormalizer::normalize($search);

        return $query->whereHas('businessReferences', function (Builder $referenceQuery) use ($normalized, $referenceType, $search): void {
            $referenceQuery
                ->where('reference_type', $referenceType)
                ->where(function (Builder $valueQuery) use ($normalized, $search): void {
                    $valueQuery->where('raw_value', 'like', "%{$search}%");

                    if (filled($normalized)) {
                        $valueQuery->orWhere('normalized_value', 'like', "%{$normalized}%");
                    }
                });
        });
    }

    private static function renderBusinessReferenceHtml(Archive $record): HtmlString
    {
        $status = static::archiveLinkageStatus($record);
        $references = app(ArchiveBusinessReferenceService::class)->visibleBusinessReferences($record);

        if ($references->isEmpty()) {
            return new HtmlString(sprintf(
                '<div class="space-y-2"><span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">%s</span><p class="text-sm text-gray-500">%s</p></div>',
                e($status['label']),
                e($status['description']),
            ));
        }

        $items = $references
            ->sortBy(fn ($reference) => $reference->categoryReferenceField?->sort_order ?? 999)
            ->map(function ($reference): string {
                $label = e($reference->categoryReferenceField?->label ?? $reference->reference_type);
                $value = e($reference->raw_value);

                return "<li><strong>{$label}:</strong> {$value}</li>";
            })
            ->implode('');

        $badgeClass = match ($status['color']) {
            'success' => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300',
            'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
            'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200',
        };

        return new HtmlString(
            '<div class="space-y-3">'.
                "<div class=\"inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {$badgeClass}\">".e($status['label']).'</div>'.
                '<p class="text-sm text-gray-500">'.e($status['description']).'</p>'.
                "<ul class=\"list-disc pl-4\">{$items}</ul>".
                '</div>',
        );
    }

    private static function renderArchivePreviewHtml(Archive $record): HtmlString
    {
        return ArchivePreviewRenderer::render($record);
    }

    private static function businessReferenceConfigurationNotice(?int $categoryId): ?HtmlString
    {
        if (! $categoryId || ! config('siardi.features.business_references')) {
            return null;
        }

        $categoryName = Category::query()->whereKey($categoryId)->value('category_name');

        if (! filled($categoryName) || ! array_key_exists($categoryName, config('siardi.supported_reconciliation_categories', []))) {
            return null;
        }

        if (static::businessReferenceFieldDefinitions($categoryId)->isNotEmpty()) {
            return null;
        }

        return new HtmlString(
            'Kategori ini sudah masuk scope target dan realisasi, tetapi field referensi bisnis belum aktif. '.
                'Jalankan <code>php artisan db:seed --class=BusinessReferenceConfigurationSeeder --force</code> di environment target.',
        );
    }

    private static function businessReferenceSectionDescription(?int $categoryId): string
    {
        if (! $categoryId) {
            return 'Pilih kategori arsip untuk menampilkan field referensi bisnis yang sesuai.';
        }

        $fields = static::businessReferenceFieldDefinitions($categoryId);

        if ($fields->isEmpty()) {
            $categoryName = Category::query()->whereKey($categoryId)->value('category_name');

            if (filled($categoryName) && array_key_exists($categoryName, config('siardi.supported_reconciliation_categories', []))) {
                return 'Kategori ini sudah didukung untuk target dan realisasi, tetapi field referensi bisnis belum dikonfigurasi.';
            }

            return 'Kategori ini tetap menggunakan perilaku legacy tanpa field referensi bisnis tambahan.';
        }

        $primaryField = $fields->firstWhere('is_primary_match_key', true);

        if (! $primaryField) {
            return 'Isi referensi bisnis yang relevan agar arsip dapat direkonsiliasi dengan data target.';
        }

        return sprintf(
            'Isi seluruh referensi bisnis. Primary match key untuk kategori ini adalah "%s".',
            $primaryField->label,
        );
    }
}
