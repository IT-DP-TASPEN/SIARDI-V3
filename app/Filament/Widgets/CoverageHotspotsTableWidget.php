<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DwhCoverageDashboard;
use App\Services\DashboardSnapshotService;
use Filament\Widgets\Widget;

class CoverageHotspotsTableWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.coverage-hotspots-table-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return false && (bool) config('siardi.features.dwh_reconciliation');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(DashboardSnapshotService::class);
        $overview = $service->getCoverageOverview(auth()->user());

        return [
            'overview' => $overview,
            'rows' => $overview['available'] ? $service->getCoverageHotspots(auth()->user()) : collect(),
            'coverageUrl' => DwhCoverageDashboard::getUrl(),
        ];
    }
}
