<?php

namespace App\Filament\Pages;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Services\ArchiveBusinessReferenceService;
use App\Services\ArchiveVisibilityService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LegacyArchiveLinker extends Page
{
    public const LINKING_MODAL_ID = 'legacy-linking-modal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected string $view = 'filament.pages.legacy-archive-linker';

    protected static ?string $navigationLabel = 'Legacy Linking';

    protected static ?string $title = 'Legacy Archive Linking';

    protected static ?int $navigationSort = 120;

    public ?string $search = null;

    public ?int $categoryId = null;

    public ?int $branchOfficeId = null;

    public ?string $uploadedFrom = null;

    public ?string $uploadedTo = null;

    public ?int $selectedArchiveId = null;

    /**
     * @var array<int|string, mixed>
     */
    public array $referenceInputs = [];

    /**
     * @var array<string, string>
     */
    public array $suggestions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $candidateResults = [];

    public bool $hasLookedUpCandidates = false;

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasLegacyLinkingAccess(auth()->user());
    }

    public static function canAccess(): bool
    {
        return static::hasLegacyLinkingAccess(auth()->user());
    }

    public function selectArchive(int $archiveId): void
    {
        $archive = $this->selectedArchiveQuery()->findOrFail($archiveId);
        $service = app(ArchiveBusinessReferenceService::class);
        $fields = $service->getFieldDefinitionsForCategory($archive->archive_category);
        $this->suggestions = $service->getLegacySuggestions($archive);
        $this->referenceInputs = [];
        $this->candidateResults = [];
        $this->hasLookedUpCandidates = false;

        foreach ($fields as $field) {
            $this->referenceInputs[$field->id] = $this->suggestions[$field->reference_type] ?? '';
        }

        $this->selectedArchiveId = $archiveId;
        $this->dispatch('open-modal', id: self::LINKING_MODAL_ID);
    }

    public function clearSelection(): void
    {
        $this->resetSelectionState();
        $this->dispatch('close-modal', id: self::LINKING_MODAL_ID);
    }

    public function handleModalClosed(string $id): void
    {
        if ($id !== self::LINKING_MODAL_ID) {
            return;
        }

        $this->resetSelectionState();
    }

    protected function resetSelectionState(): void
    {
        $this->selectedArchiveId = null;
        $this->referenceInputs = [];
        $this->suggestions = [];
        $this->candidateResults = [];
        $this->hasLookedUpCandidates = false;
    }

    public function lookupCandidates(): void
    {
        if (! $this->selectedArchiveId) {
            return;
        }

        $archive = $this->selectedArchiveQuery()->findOrFail($this->selectedArchiveId);

        if (! ($archive->branchOffice?->dwhMapping?->is_active)) {
            $this->candidateResults = [];
            $this->hasLookedUpCandidates = false;

            Notification::make()
                ->title('Lookup kandidat diblok')
                ->body('Cabang arsip ini belum punya mapping target aktif.')
                ->warning()
                ->send();

            return;
        }

        $this->candidateResults = app(ArchiveBusinessReferenceService::class)
            ->lookupCandidatesForArchive($archive, $this->referenceInputs)
            ->all();
        $this->hasLookedUpCandidates = true;
    }

    public function applyCandidate(int $candidateIndex): void
    {
        if (! isset($this->candidateResults[$candidateIndex]) || ! $this->selectedArchiveId) {
            return;
        }

        $archive = $this->selectedArchiveQuery()->findOrFail($this->selectedArchiveId);
        $fields = app(ArchiveBusinessReferenceService::class)->getFieldDefinitionsForCategory($archive->archive_category);
        $candidate = $this->candidateResults[$candidateIndex];

        foreach ($fields as $field) {
            $this->referenceInputs[$field->id] = $candidate['reference_values'][$field->reference_type] ?? '';
        }

        Notification::make()
            ->title('Kandidat diterapkan ke form')
            ->success()
            ->send();
    }

    public function saveReferences(): void
    {
        $this->validate($this->referenceValidationRules(), [], $this->referenceValidationAttributes());

        $archive = $this->selectedArchiveQuery()->findOrFail($this->selectedArchiveId);
        $service = app(ArchiveBusinessReferenceService::class);
        $validationErrors = $service->validateReferencesForArchive($archive, $this->referenceInputs);

        if ($validationErrors !== []) {
            throw ValidationException::withMessages($validationErrors);
        }

        $service->syncForArchive($archive, $this->referenceInputs);

        Notification::make()
            ->title('Referensi berhasil disimpan')
            ->success()
            ->send();

        $this->resetSelectionState();
        $this->dispatch('close-modal', id: self::LINKING_MODAL_ID);
    }

    protected function getViewData(): array
    {
        $service = app(ArchiveBusinessReferenceService::class);
        $visibility = app(ArchiveVisibilityService::class);
        $user = auth()->user();
        $visibleCategoryIds = $visibility->visibleCategoryIds($user);
        $visibleBranchOfficeIds = $visibility->visibleBranchOfficeIds($user);
        $selectedArchive = $this->selectedArchiveId
            ? $this->selectedArchiveQuery()->find($this->selectedArchiveId)
            : null;
        $referenceFields = $selectedArchive
            ? $service->getFieldDefinitionsForCategory($selectedArchive->archive_category)
            : collect();

        return [
            'archives' => $this->scopedArchivesQuery()
                ->doesntHave('businessReferences')
                ->when($this->categoryId, fn ($query) => $query->where('archive_category', $this->categoryId))
                ->when($this->branchOfficeId, fn ($query) => $query->where('archive_branch_office', $this->branchOfficeId))
                ->when($this->uploadedFrom, fn ($query) => $query->whereDate('created_at', '>=', $this->uploadedFrom))
                ->when($this->uploadedTo, fn ($query) => $query->whereDate('created_at', '<=', $this->uploadedTo))
                ->when($this->search, function ($query): void {
                    $query->where(function ($innerQuery): void {
                        $innerQuery
                            ->where('archive_name', 'like', '%'.$this->search.'%')
                            ->orWhere('archive_code', 'like', '%'.$this->search.'%')
                            ->orWhere('archive_path', 'like', '%'.$this->search.'%');
                    });
                })
                ->latest('created_at')
                ->limit(50)
                ->get(),
            'categories' => Category::query()
                ->whereIn('id', $visibleCategoryIds)
                ->orderBy('category_name')
                ->get(),
            'branches' => BranchOffice::query()
                ->whereIn('id', $visibleBranchOfficeIds)
                ->orderBy('branch_code')
                ->get(),
            'selectedArchive' => $selectedArchive,
            'referenceFields' => $referenceFields,
            'configurationGaps' => $this->configurationGaps(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function referenceValidationRules(): array
    {
        if (! $this->selectedArchiveId) {
            return [];
        }

        $archive = $this->selectedArchiveQuery()->find($this->selectedArchiveId);

        if (! $archive) {
            return [];
        }

        return app(ArchiveBusinessReferenceService::class)
            ->getFieldDefinitionsForCategory($archive->archive_category)
            ->mapWithKeys(fn ($field): array => [
                "referenceInputs.{$field->id}" => array_filter([
                    $field->is_required ? 'required' : 'nullable',
                    'string',
                    'max:255',
                ]),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function referenceValidationAttributes(): array
    {
        if (! $this->selectedArchiveId) {
            return [];
        }

        $archive = $this->selectedArchiveQuery()->find($this->selectedArchiveId);

        if (! $archive) {
            return [];
        }

        return app(ArchiveBusinessReferenceService::class)
            ->getFieldDefinitionsForCategory($archive->archive_category)
            ->mapWithKeys(fn ($field): array => ["referenceInputs.{$field->id}" => $field->label])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function configurationGaps(): array
    {
        $supportedCategoryNames = array_keys(config('siardi.supported_reconciliation_categories', []));
        $visibleSupportedCategoryIds = app(ArchiveVisibilityService::class)->visibleSupportedCategoryIds(auth()->user());

        return Category::query()
            ->whereIn('id', $visibleSupportedCategoryIds)
            ->whereIn('category_name', $supportedCategoryNames)
            ->withCount('referenceFields')
            ->orderBy('category_name')
            ->get()
            ->filter(fn (Category $category): bool => $category->reference_fields_count === 0)
            ->pluck('category_name')
            ->values()
            ->all();
    }

    protected function scopedArchivesQuery(): Builder
    {
        return app(ArchiveVisibilityService::class)->applyArchiveScope(
            Archive::query()->with(['category', 'branchOffice']),
            auth()->user(),
        );
    }

    protected function selectedArchiveQuery(): Builder
    {
        return app(ArchiveVisibilityService::class)->applyArchiveScope(
            Archive::query()->with(['category', 'branchOffice.dwhMapping']),
            auth()->user(),
        );
    }

    protected static function hasLegacyLinkingAccess(mixed $user): bool
    {
        if (! config('siardi.features.legacy_reference_linking') || ! $user) {
            return false;
        }

        return app(ArchiveVisibilityService::class)->visibleSupportedCategoryIds($user) !== [];
    }
}
