<?php

namespace App\Filament\Widgets;

use App\Services\DashboardSnapshotService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ArchiveOperationsStatsWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Ringkasan Arsip';

    protected ?string $description = 'Snapshot cepat volume arsip sesuai scope akses user.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $overview = app(DashboardSnapshotService::class)->getArchiveOverview(auth()->user());

        return [
            Stat::make('Total Arsip', number_format($overview['total_archives']))
                ->description('Seluruh arsip yang terlihat oleh user ini.')
                ->color('primary')
                ->icon('heroicon-o-document-text'),
            Stat::make('Upload Bulan Ini', number_format($overview['uploads_this_month']))
                ->description('Arsip baru sejak awal bulan.')
                ->color('success')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('7 Hari Terakhir', number_format($overview['uploads_last_7_days']))
                ->description('Upload baru dalam 7 hari terakhir.')
                ->color('warning')
                ->icon('heroicon-o-arrow-trending-up'),
            Stat::make('Belum Ditautkan', number_format($overview['unlinked_supported_archives']))
                ->description('Arsip kategori rekonsiliasi yang belum punya business reference.')
                ->color($overview['unlinked_supported_archives'] > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-link-slash'),
        ];
    }
}
