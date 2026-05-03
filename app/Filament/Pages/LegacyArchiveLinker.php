<?php

namespace App\Filament\Pages;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Services\ArchiveBusinessReferenceService;
use App\Services\ArchiveVisibilityService;
use App\Support\ArchivePreviewRenderer;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;

class LegacyArchiveLinker extends Page
{
    use HasPageShield {
        canAccess as protected canAccessViaShield;
    }
    use WithPagination;

    public const LINKING_MODAL_ID = 'legacy-linking-modal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected string $view = 'filament.pages.legacy-archive-linker';

    protected static ?string $navigationLabel = 'Legacy Linking';

    protected static ?string $title = 'Legacy Archive Linking';

    protected static ?int $navigationSort = 120;

    protected int $perPage = 50;

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
     * @var array<string, mixed>
     */
    public array $candidateSearchInputs = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $candidateResults = [];

    public bool $hasLookedUpCandidates = false;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return static::canAccessViaShield() && static::hasLegacyLinkingAccess(auth()->user());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedBranchOfficeId(): void
    {
        $this->resetPage();
    }

    public function updatedUploadedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedUploadedTo(): void
    {
        $this->resetPage();
    }

    public function selectArchive(int $archiveId): void
    {
        $archive = $this->selectedArchiveQuery()->findOrFail($archiveId);
        $service = app(ArchiveBusinessReferenceService::class);
        $fields = $service->getFieldDefinitionsForCategory($archive->archive_category);
        $this->suggestions = $service->getLegacySuggestions($archive);
        $this->referenceInputs = [];
        $this->candidateSearchInputs = [
            'savings_alt_account_no' => $this->suggestions['savings_alt_account_no'] ?? '',
            'loan_alt_account_no' => $this->suggestions['loan_alt_account_no'] ?? '',
        ];
        $this->candidateResults = [];
        $this->hasLookedUpCandidates = false;

        foreach ($fields as $field) {
            $this->referenceInputs[$field->id] = $this->suggestions[$field->reference_type] ?? '';
        }

        $this->selectedArchiveId = $archiveId;
        $this->dispatch('open-modal', id: self::LINKING_MODAL_ID);
    }

    public function markInactive(): void
    {
        if (! $this->selectedArchiveId) {
            return;
        }

        $archive = $this->selectedArchiveQuery()->findOrFail($this->selectedArchiveId);

        $archive->legacyInactiveMarker()->updateOrCreate(
            ['archive_id' => $archive->id],
            [
                'marked_inactive_by' => auth()->id(),
                'marked_inactive_at' => now(),
            ],
        );

        Notification::make()
            ->title('Arsip ditandai inactive')
            ->success()
            ->send();

        $this->resetSelectionState();
        $this->dispatch('close-modal', id: self::LINKING_MODAL_ID);
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
        $this->candidateSearchInputs = [];
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
            ->lookupCandidatesForArchive($archive, $this->referenceInputs, $this->candidateSearchInputs)
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

        if (isset($candidate['reference_values']['loan_alt_account_no'])) {
            $this->candidateSearchInputs['loan_alt_account_no'] = $candidate['reference_values']['loan_alt_account_no'];
        }

        if (isset($candidate['reference_values']['savings_alt_account_no'])) {
            $this->candidateSearchInputs['savings_alt_account_no'] = $candidate['reference_values']['savings_alt_account_no'];
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
            'archives' => $this->filteredArchivesQuery()->paginate($this->perPage),
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
            'archivePreviewHtml' => $selectedArchive ? ArchivePreviewRenderer::render($selectedArchive, '60vh') : null,
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
        $referenceService = app(ArchiveBusinessReferenceService::class);

        return Category::query()
            ->whereIn('id', $visibleSupportedCategoryIds)
            ->whereIn('category_name', $supportedCategoryNames)
            ->orderBy('category_name')
            ->get()
            ->filter(fn (Category $category): bool => $referenceService->getFieldDefinitionsForCategory($category->id)->isEmpty())
            ->pluck('category_name')
            ->values()
            ->all();
    }

    protected function filteredArchivesQuery(): Builder
    {
        return $this->queueArchivesQuery()
            ->when($this->categoryId, fn (Builder $query) => $query->where('archive_category', $this->categoryId))
            ->when($this->branchOfficeId, fn (Builder $query) => $query->where('archive_branch_office', $this->branchOfficeId))
            ->when($this->uploadedFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->uploadedFrom))
            ->when($this->uploadedTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->uploadedTo))
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $innerQuery): void {
                    $innerQuery
                        ->where('archive_name', 'like', '%'.$this->search.'%')
                        ->orWhere('archive_code', 'like', '%'.$this->search.'%')
                        ->orWhere('archive_path', 'like', '%'.$this->search.'%');
                });
            })
            ->latest('created_at');
    }

    protected function queueArchivesQuery(): Builder
    {
        return $this->scopedArchivesQuery(['category', 'branchOffice'])
            ->whereDoesntHave('legacyInactiveMarker')
            ->whereDoesntHave('businessReferences', function (Builder $query): void {
                $query->whereNotIn('reference_type', app(ArchiveBusinessReferenceService::class)->deprecatedReferenceTypes());
            });
    }

    protected function selectedArchiveQuery(): Builder
    {
        return $this->scopedArchivesQuery(['category', 'branchOffice.dwhMapping', 'legacyInactiveMarker'])
            ->whereDoesntHave('legacyInactiveMarker');
    }

    protected function scopedArchivesQuery(array $relations): Builder
    {
        return app(ArchiveVisibilityService::class)->applyArchiveScope(
            Archive::query()->with($relations),
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
