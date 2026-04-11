<?php

namespace App\Filament\Pages;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RekapanArsip extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    public static ?string $slug = 'archive-recap';

    public static ?string $label = 'Archive Recap';

    protected static ?string $navigationLabel = 'Archive Recap';

    protected static ?string $breadcrumb = 'Archive Recap';

    protected string $view = 'filament.pages.rekapan-arsip';

    protected static ?int $navigationSort = 100;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printLaporan')
                ->label('Print')
                ->color('primary')
                ->icon('heroicon-o-printer')
                ->url(route('rekap.print'))
                ->openUrlInNewTab(),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'rekap' => $this->buildRecap(),
        ];
    }

    /**
     * @return Collection<string, array<string, int>>
     */
    private function buildRecap(): Collection
    {
        $categoryIds = Category::query()
            ->whereIn('category_name', ['TABUNGAN', 'BILYET DEPOSITO', 'KREDIT'])
            ->pluck('id', 'category_name');

        return BranchOffice::query()
            ->withCount([
                'archives as tabungan_count' => fn ($query) => $query->where('archive_category', $categoryIds['TABUNGAN'] ?? 0),
                'archives as deposito_count' => fn ($query) => $query->where('archive_category', $categoryIds['BILYET DEPOSITO'] ?? 0),
                'archives as kredit_count' => fn ($query) => $query->where('archive_category', $categoryIds['KREDIT'] ?? 0),
            ])
            ->whereNotIn('branch_code', ['00', '09'])
            ->get()
            ->mapWithKeys(function (BranchOffice $branch): array {
                $branchName = $this->formatBranchName($branch->branch_name);

                return [
                    $branchName => [
                        'tabungan' => (int) $branch->tabungan_count,
                        'deposito' => (int) $branch->deposito_count,
                        'kredit' => (int) $branch->kredit_count,
                        'total' => (int) $branch->tabungan_count + (int) $branch->deposito_count + (int) $branch->kredit_count,
                    ],
                ];
            });
    }

    private function formatBranchName(string $branchName): string
    {
        $parts = explode(' ', $branchName);

        if (count($parts) <= 2) {
            return $branchName;
        }

        if (Str::lower($parts[1]) === 'pusat') {
            return 'KP. '.implode(' ', array_slice($parts, 2));
        }

        return 'KC. '.implode(' ', array_slice($parts, 2));
    }
}
