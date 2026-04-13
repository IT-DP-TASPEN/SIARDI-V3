<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\DwhBranchMapping;
use App\Repositories\DwhCoverageRepository;
use App\Services\ArchiveVisibilityService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class DwhCoverageDashboard extends Page
{
    use HasPageShield {
        canAccess as protected canAccessViaShield;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected string $view = 'filament.pages.dwh-coverage-dashboard';

    protected static ?string $navigationLabel = 'Target & Realisasi';

    protected static ?string $title = 'Target & Realisasi Arsip';

    protected static ?int $navigationSort = 110;

    public ?int $selectedCategoryId = null;

    public ?int $selectedBranchOfficeId = null;

    public ?int $drilldownCategoryId = null;

    public ?int $drilldownBranchOfficeId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return (bool) config('siardi.features.dwh_reconciliation') && static::canAccessViaShield();
    }

    public function openDrilldown(int $categoryId, int $branchOfficeId): void
    {
        $visibility = app(ArchiveVisibilityService::class);
        $user = auth()->user();

        if (! $visibility->isCategoryVisible($user, $categoryId, true) || ! $visibility->isBranchVisible($user, $branchOfficeId)) {
            $this->clearDrilldown();

            return;
        }

        $this->drilldownCategoryId = $categoryId;
        $this->drilldownBranchOfficeId = $branchOfficeId;
    }

    public function clearDrilldown(): void
    {
        $this->drilldownCategoryId = null;
        $this->drilldownBranchOfficeId = null;
    }

    protected function getViewData(): array
    {
        $repository = app(DwhCoverageRepository::class);
        $visibility = app(ArchiveVisibilityService::class);
        $user = auth()->user();
        $visibleCategoryIds = $visibility->visibleSupportedCategoryIds($user);
        $visibleBranchOfficeIds = $visibility->visibleBranchOfficeIds($user);
        $selectedCategoryInScope = ! $this->selectedCategoryId || $visibility->isCategoryVisible($user, $this->selectedCategoryId, true);
        $selectedBranchInScope = ! $this->selectedBranchOfficeId || $visibility->isBranchVisible($user, $this->selectedBranchOfficeId);

        $summaryRows = collect();

        if ($selectedCategoryInScope && $selectedBranchInScope) {
            $summaryRows = $visibility->filterCoverageRows(
                $repository->getCoverageSummary(
                    branchOfficeId: $this->selectedBranchOfficeId,
                    categoryId: $this->selectedCategoryId,
                ),
                $user,
            );
        }

        $missingRows = collect();

        if (
            $this->drilldownCategoryId
            && $this->drilldownBranchOfficeId
            && $visibility->isCategoryVisible($user, $this->drilldownCategoryId, true)
            && $visibility->isBranchVisible($user, $this->drilldownBranchOfficeId)
        ) {
            $missingRows = $repository->getMissingRecords($this->drilldownCategoryId, $this->drilldownBranchOfficeId);
        }

        return [
            'categories' => Category::query()
                ->whereIn('id', $visibleCategoryIds)
                ->orderBy('category_name')
                ->get(),
            'branchMappings' => DwhBranchMapping::query()
                ->with('branchOffice')
                ->where('is_active', true)
                ->whereIn('branch_office_id', $visibleBranchOfficeIds)
                ->orderBy('siardi_branch_code')
                ->get(),
            'summaryRows' => $summaryRows,
            'missingRows' => $missingRows,
        ];
    }
}
