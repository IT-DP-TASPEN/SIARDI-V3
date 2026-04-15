<?php

namespace App\Filament\Pages;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\LegacyArchiveInactive;
use App\Services\ArchiveVisibilityService;
use App\Support\ArchivePreviewRenderer;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

class LegacyInactiveArchives extends Page
{
    use HasPageShield {
        canAccess as protected canAccessViaShield;
    }
    use WithPagination;

    public const DETAIL_MODAL_ID = 'legacy-inactive-archive-modal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected string $view = 'filament.pages.legacy-inactive-archives';

    protected static ?string $navigationLabel = 'Arsip Inactive';

    protected static ?string $title = 'Arsip Inactive';

    protected static ?int $navigationSort = 121;

    protected int $perPage = 50;

    public ?string $search = null;

    public ?int $categoryId = null;

    public ?int $branchOfficeId = null;

    public ?string $uploadedFrom = null;

    public ?string $uploadedTo = null;

    public ?int $selectedArchiveId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        if (! config('siardi.features.legacy_reference_linking')) {
            return false;
        }

        $user = auth()->user();

        return $user
            && static::canAccessViaShield()
            && app(ArchiveVisibilityService::class)->visibleSupportedCategoryIds($user) !== [];
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
        $this->selectedArchiveQuery()->findOrFail($archiveId);
        $this->selectedArchiveId = $archiveId;
        $this->dispatch('open-modal', id: self::DETAIL_MODAL_ID);
    }

    public function restoreArchive(): void
    {
        if (! $this->selectedArchiveId) {
            return;
        }

        $archive = $this->selectedArchiveQuery()->findOrFail($this->selectedArchiveId);
        $archive->legacyInactiveMarker()?->delete();

        Notification::make()
            ->title('Arsip dikembalikan ke Legacy Linking')
            ->success()
            ->send();

        $this->resetSelectionState();
        $this->dispatch('close-modal', id: self::DETAIL_MODAL_ID);
    }

    public function clearSelection(): void
    {
        $this->resetSelectionState();
        $this->dispatch('close-modal', id: self::DETAIL_MODAL_ID);
    }

    public function handleModalClosed(string $id): void
    {
        if ($id !== self::DETAIL_MODAL_ID) {
            return;
        }

        $this->resetSelectionState();
    }

    protected function resetSelectionState(): void
    {
        $this->selectedArchiveId = null;
    }

    protected function getViewData(): array
    {
        $visibility = app(ArchiveVisibilityService::class);
        $user = auth()->user();
        $visibleCategoryIds = $visibility->visibleCategoryIds($user);
        $visibleBranchOfficeIds = $visibility->visibleBranchOfficeIds($user);
        $selectedArchive = $this->selectedArchiveId
            ? $this->selectedArchiveQuery()->find($this->selectedArchiveId)
            : null;

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
            'archivePreviewHtml' => $selectedArchive ? ArchivePreviewRenderer::render($selectedArchive, '60vh') : null,
        ];
    }

    protected function filteredArchivesQuery(): Builder
    {
        return $this->selectedArchiveQuery()
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
            ->orderByDesc(
                LegacyArchiveInactive::query()
                    ->select('marked_inactive_at')
                    ->whereColumn('legacy_archive_inactives.archive_id', 'archives.id')
                    ->limit(1),
            )
            ->latest('created_at');
    }

    protected function selectedArchiveQuery(): Builder
    {
        return app(ArchiveVisibilityService::class)->applyArchiveScope(
            Archive::query()->with(['category', 'branchOffice', 'legacyInactiveMarker.markedBy']),
            auth()->user(),
        )->whereHas('legacyInactiveMarker');
    }
}
