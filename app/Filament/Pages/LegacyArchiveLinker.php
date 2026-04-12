<?php

namespace App\Filament\Pages;

use App\Models\Archive;
use App\Models\Category;
use App\Models\BranchOffice;
use App\Services\ArchiveBusinessReferenceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LegacyArchiveLinker extends Page
{
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
        return (bool) config('siardi.features.legacy_reference_linking');
    }

    public static function canAccess(): bool
    {
        return (bool) config('siardi.features.legacy_reference_linking') && (auth()->user()?->isAdmin() ?? false);
    }

    public function selectArchive(int $archiveId): void
    {
        $archive = Archive::query()->with(['category', 'branchOffice.dwhMapping'])->findOrFail($archiveId);
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
        $this->dispatch('legacy-linking-archive-selected');
    }

    public function clearSelection(): void
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

        $archive = Archive::query()->with(['category', 'branchOffice.dwhMapping'])->findOrFail($this->selectedArchiveId);
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

        $archive = Archive::query()->findOrFail($this->selectedArchiveId);
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

        $archive = Archive::query()->findOrFail($this->selectedArchiveId);
        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, $this->referenceInputs);

        Notification::make()
            ->title('Referensi berhasil disimpan')
            ->success()
            ->send();

        $this->selectedArchiveId = null;
        $this->referenceInputs = [];
        $this->suggestions = [];
        $this->candidateResults = [];
        $this->hasLookedUpCandidates = false;
    }

    protected function getViewData(): array
    {
        $service = app(ArchiveBusinessReferenceService::class);
        $selectedArchive = $this->selectedArchiveId
            ? Archive::query()->with(['category', 'branchOffice.dwhMapping'])->find($this->selectedArchiveId)
            : null;
        $referenceFields = $selectedArchive
            ? $service->getFieldDefinitionsForCategory($selectedArchive->archive_category)
            : collect();

        return [
            'archives' => Archive::query()
                ->with(['category', 'branchOffice'])
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
            'categories' => Category::query()->orderBy('category_name')->get(),
            'branches' => BranchOffice::query()->orderBy('branch_code')->get(),
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

        $archive = Archive::query()->find($this->selectedArchiveId);

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

        $archive = Archive::query()->find($this->selectedArchiveId);

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

        return Category::query()
            ->whereIn('category_name', $supportedCategoryNames)
            ->withCount('referenceFields')
            ->orderBy('category_name')
            ->get()
            ->filter(fn (Category $category): bool => $category->reference_fields_count === 0)
            ->pluck('category_name')
            ->values()
            ->all();
    }
}
