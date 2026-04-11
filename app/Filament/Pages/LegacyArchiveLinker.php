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
        $archive = Archive::query()->with(['category', 'branchOffice'])->findOrFail($archiveId);
        $service = app(ArchiveBusinessReferenceService::class);
        $fields = $service->getFieldDefinitionsForCategory($archive->archive_category);
        $this->suggestions = $service->getLegacySuggestions($archive);
        $this->referenceInputs = [];

        foreach ($fields as $field) {
            $this->referenceInputs[$field->id] = $this->suggestions[$field->reference_type] ?? '';
        }

        $this->selectedArchiveId = $archiveId;
    }

    public function saveReferences(): void
    {
        $archive = Archive::query()->findOrFail($this->selectedArchiveId);
        app(ArchiveBusinessReferenceService::class)->syncForArchive($archive, $this->referenceInputs);

        Notification::make()
            ->title('Referensi berhasil disimpan')
            ->success()
            ->send();

        $this->selectedArchiveId = null;
        $this->referenceInputs = [];
        $this->suggestions = [];
    }

    protected function getViewData(): array
    {
        $service = app(ArchiveBusinessReferenceService::class);
        $selectedArchive = $this->selectedArchiveId
            ? Archive::query()->with(['category', 'branchOffice'])->find($this->selectedArchiveId)
            : null;

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
            'referenceFields' => $selectedArchive
                ? $service->getFieldDefinitionsForCategory($selectedArchive->archive_category)
                : collect(),
        ];
    }
}
