<?php

namespace App\Filament\Widgets;

use App\Services\DashboardSnapshotService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DwhCoverageStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Ringkasan Coverage DWH';

    protected ?string $description = 'Target, realized, dan missing berdasarkan distinct business record pada mirror DWH harian.';

    public static function canView(): bool
    {
        return (bool) config('siardi.features.dwh_reconciliation');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $overview = app(DashboardSnapshotService::class)->getCoverageOverview(auth()->user());

        if (! $overview['available']) {
            return [
                Stat::make('DWH', 'Unavailable')
                    ->description($overview['message'])
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-triangle'),
            ];
        }

        return [
            Stat::make('Target', number_format($overview['target_total']))
                ->description('Data mirror DWH harian.')
                ->color('primary')
                ->icon('heroicon-o-circle-stack'),
            Stat::make('Realized', number_format($overview['realized_total']))
                ->description('Distinct business record yang sudah covered.')
                ->color('success')
                ->icon('heroicon-o-check-badge'),
            Stat::make('Missing', number_format($overview['missing_total']))
                ->description('Target DWH yang belum punya arsip tertaut.')
                ->color($overview['missing_total'] > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Coverage', number_format($overview['coverage_percentage'], 2) . '%')
                ->description('Persentase realized terhadap target.')
                ->color($overview['coverage_percentage'] >= 95 ? 'success' : ($overview['coverage_percentage'] >= 75 ? 'warning' : 'danger'))
                ->icon('heroicon-o-chart-bar-square'),
        ];
    }
}
