<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DwhCoverageDashboard;
use App\Services\DashboardSnapshotService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class CoverageHotspotsTableWidget extends Widget
{
    use HasWidgetShield {
        canView as protected canViewViaShield;
    }

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.coverage-hotspots-table-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) config('siardi.features.dwh_reconciliation') && static::canViewViaShield();
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
