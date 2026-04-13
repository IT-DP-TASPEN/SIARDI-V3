<?php

namespace App\Filament\Widgets;

use App\Services\DashboardSnapshotService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ArchiveUploadsTrendWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Trend Upload Arsip';

    protected ?string $description = 'Volume upload harian untuk scope data user saat ini.';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '30';

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            '7' => '7 Hari',
            '30' => '30 Hari',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?: 30);
        $trend = app(DashboardSnapshotService::class)->getArchiveUploadTrend(auth()->user(), $days);

        return [
            'datasets' => [
                [
                    'label' => 'Upload Arsip',
                    'data' => $trend['values'],
                    'fill' => 'start',
                    'tension' => 0.35,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
