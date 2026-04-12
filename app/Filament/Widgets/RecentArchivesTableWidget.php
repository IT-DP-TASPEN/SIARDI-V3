<?php

namespace App\Filament\Widgets;

use App\Services\DashboardSnapshotService;
use Filament\Widgets\Widget;

class RecentArchivesTableWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.recent-archives-table-widget';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'rows' => app(DashboardSnapshotService::class)->getRecentArchives(auth()->user()),
        ];
    }
}
