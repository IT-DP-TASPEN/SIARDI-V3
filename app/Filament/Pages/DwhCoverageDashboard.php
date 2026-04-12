<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\DwhBranchMapping;
use App\Repositories\DwhCoverageRepository;
use Filament\Pages\Page;

class DwhCoverageDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected string $view = 'filament.pages.dwh-coverage-dashboard';

    protected static ?string $navigationLabel = 'DWH Coverage';

    protected static ?string $title = 'DWH Coverage Dashboard';

    protected static ?int $navigationSort = 110;

    public ?int $selectedCategoryId = null;

    public ?int $selectedBranchOfficeId = null;

    public ?int $drilldownCategoryId = null;

    public ?int $drilldownBranchOfficeId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('siardi.features.dwh_reconciliation');
    }

    public static function canAccess(): bool
    {
        return (bool) config('siardi.features.dwh_reconciliation');
    }

    public function openDrilldown(int $categoryId, int $branchOfficeId): void
    {
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

        return [
            'categories' => Category::query()
                ->whereIn('category_name', array_keys(config('siardi.supported_reconciliation_categories')))
                ->orderBy('category_name')
                ->get(),
            'branchMappings' => DwhBranchMapping::query()
                ->with('branchOffice')
                ->where('is_active', true)
                ->orderBy('siardi_branch_code')
                ->get(),
            'summaryRows' => $repository->getCoverageSummary(
                branchOfficeId: $this->selectedBranchOfficeId,
                categoryId: $this->selectedCategoryId,
            ),
            'missingRows' => $this->drilldownCategoryId && $this->drilldownBranchOfficeId
                ? $repository->getMissingRecords($this->drilldownCategoryId, $this->drilldownBranchOfficeId)
                : collect(),
        ];
    }
}
