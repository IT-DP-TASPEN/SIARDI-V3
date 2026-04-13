<?php

namespace App\Filament\Widgets;

use App\Services\DashboardSnapshotService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DwhCoverageStatsWidget extends StatsOverviewWidget
{
    use HasWidgetShield {
        canView as protected canViewViaShield;
    }

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Ringkasan Target & Realisasi';

    protected ?string $description = 'Target, realisasi, dan missing berdasarkan distinct business record pada data target harian.';

    public static function canView(): bool
    {
        return (bool) config('siardi.features.dwh_reconciliation') && static::canViewViaShield();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $overview = app(DashboardSnapshotService::class)->getCoverageOverview(auth()->user());

        if (! $overview['available']) {
            return [
                Stat::make('Data Target', 'Unavailable')
                    ->description($overview['message'])
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-triangle'),
            ];
        }

        return [
            Stat::make('Target', number_format($overview['target_total']))
                ->description('Data target harian.')
                ->color('primary')
                ->icon('heroicon-o-circle-stack'),
            Stat::make('Realisasi', number_format($overview['realized_total']))
                ->description('Distinct business record yang sudah covered.')
                ->color('success')
                ->icon('heroicon-o-check-badge'),
            Stat::make('Missing', number_format($overview['missing_total']))
                ->description('Target yang belum punya arsip tertaut.')
                ->color($overview['missing_total'] > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Coverage', number_format($overview['coverage_percentage'], 2).'%')
                ->description('Persentase realisasi terhadap target.')
                ->color($overview['coverage_percentage'] >= 95 ? 'success' : ($overview['coverage_percentage'] >= 75 ? 'warning' : 'danger'))
                ->icon('heroicon-o-chart-bar-square'),
        ];
    }
}
