<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArchiveOperationsStatsWidget;
use App\Filament\Widgets\ArchiveUploadsTrendWidget;
use App\Filament\Widgets\CoverageHotspotsTableWidget;
use App\Filament\Widgets\DwhCoverageStatsWidget;
use App\Filament\Widgets\RecentArchivesTableWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Dashboard';

    protected ?string $heading = 'SIARDI Dashboard';

    protected ?string $subheading = 'Ringkasan operasional arsip.';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ArchiveOperationsStatsWidget::class,
            DwhCoverageStatsWidget::class,
            ArchiveUploadsTrendWidget::class,
            RecentArchivesTableWidget::class,
            CoverageHotspotsTableWidget::class,
        ];
    }

    /**
     * @return int | array<string, int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 12,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archiveRecap')
                ->label('Archive Recap')
                ->color('gray')
                ->icon('heroicon-o-presentation-chart-line')
                ->url(RekapanArsip::getUrl())
                ->visible(fn (): bool => RekapanArsip::canAccess()),
            Action::make('dwhCoverage')
                ->label('Target & Realisasi')
                ->color('primary')
                ->icon('heroicon-o-chart-bar-square')
                ->url(DwhCoverageDashboard::getUrl())
                ->visible(fn (): bool => DwhCoverageDashboard::canAccess()),
            Action::make('legacyLinking')
                ->label('Legacy Linking')
                ->color('gray')
                ->icon('heroicon-o-link')
                ->url(LegacyArchiveLinker::getUrl())
                ->visible(fn (): bool => LegacyArchiveLinker::canAccess()),
            Action::make('inactiveArchives')
                ->label('Arsip Inactive')
                ->color('gray')
                ->icon('heroicon-o-archive-box')
                ->url(LegacyInactiveArchives::getUrl())
                ->visible(fn (): bool => LegacyInactiveArchives::canAccess()),
        ];
    }
}
