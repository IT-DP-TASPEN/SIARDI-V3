<?php

namespace App\Services;

use App\Filament\Resources\ArchiveResource;
use App\Models\Archive;
use App\Models\Category;
use App\Models\User;
use App\Repositories\DwhCoverageRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardSnapshotService
{
    public function __construct(
        private readonly ArchiveBusinessReferenceService $archiveBusinessReferenceService,
        private readonly ArchiveVisibilityService $archiveVisibilityService,
        private readonly DwhCoverageRepository $dwhCoverageRepository,
    ) {}

    /**
     * @return array<string, int>
     */
    public function getArchiveOverview(User $user): array
    {
        $query = $this->visibleArchivesQuery($user);
        $supportedCategoryIds = $this->supportedCategoryIds();

        return [
            'total_archives' => (clone $query)->count(),
            'uploads_this_month' => (clone $query)
                ->whereDate('created_at', '>=', now()->startOfMonth()->toDateString())
                ->count(),
            'uploads_last_7_days' => (clone $query)
                ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
                ->count(),
            'unlinked_supported_archives' => (clone $query)
                ->whereIn('archive_category', $supportedCategoryIds)
                ->whereDoesntHave('legacyInactiveMarker')
                ->whereDoesntHave('businessReferences', function ($referenceQuery): void {
                    $referenceQuery->whereNotIn(
                        'reference_type',
                        $this->archiveBusinessReferenceService->deprecatedReferenceTypes(),
                    );
                })
                ->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function getArchiveUploadTrend(User $user, int $days = 30): array
    {
        $days = max($days, 1);
        $startDate = now()->startOfDay()->subDays($days - 1);

        $counts = (clone $this->visibleArchivesQuery($user))
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->selectRaw('DATE(created_at) as upload_date, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('upload_date')
            ->pluck('total', 'upload_date');

        $labels = [];
        $values = [];

        foreach (range(0, $days - 1) as $offset) {
            $date = $startDate->copy()->addDays($offset);
            $key = $date->toDateString();

            $labels[] = $date->format('d M');
            $values[] = (int) ($counts[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRecentArchives(User $user, int $limit = 8): Collection
    {
        return (clone $this->visibleArchivesQuery($user))
            ->with([
                'category',
                'branchOffice',
                'businessReferences.categoryReferenceField',
                'legacyInactiveMarker',
            ])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Archive $archive): array {
                return [
                    'archive' => $archive,
                    'linkage_status' => $this->archiveBusinessReferenceService->getLinkageStatus($archive),
                    'view_url' => ArchiveResource::getUrl('view', ['record' => $archive]),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function getCoverageOverview(User $user): array
    {
        if (! config('siardi.features.dwh_reconciliation')) {
            return [
                'enabled' => false,
                'available' => false,
                'message' => 'Target dan realisasi nonaktif.',
                'target_total' => 0,
                'realized_total' => 0,
                'missing_total' => 0,
                'coverage_percentage' => 0.0,
            ];
        }

        try {
            $summary = $this->visibleCoverageSummary($user);
        } catch (\Throwable) {
            return [
                'enabled' => true,
                'available' => false,
                'message' => 'Data target unavailable. Periksa koneksi read-only sumber data.',
                'target_total' => 0,
                'realized_total' => 0,
                'missing_total' => 0,
                'coverage_percentage' => 0.0,
            ];
        }

        $targetTotal = (int) $summary->sum('target_count');
        $realizedTotal = (int) $summary->sum('realized_count');
        $missingTotal = (int) $summary->sum('missing_count');

        return [
            'enabled' => true,
            'available' => true,
            'message' => $summary->isEmpty() ? 'Belum ada data target dan realisasi untuk scope user ini.' : null,
            'target_total' => $targetTotal,
            'realized_total' => $realizedTotal,
            'missing_total' => $missingTotal,
            'coverage_percentage' => $targetTotal > 0 ? round(($realizedTotal / $targetTotal) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getCoverageHotspots(User $user, int $limit = 8): Collection
    {
        try {
            return $this->visibleCoverageSummary($user)
                ->filter(fn (array $row): bool => (int) $row['target_count'] > 0)
                ->sortBy([
                    ['missing_count', 'desc'],
                    ['coverage_percentage', 'asc'],
                    ['branch_code', 'asc'],
                ])
                ->take($limit)
                ->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function visibleArchivesQuery(User $user): Builder
    {
        return $this->archiveVisibilityService->applyArchiveScope(
            Archive::query()->with(['category', 'branchOffice', 'legacyInactiveMarker']),
            $user,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleCoverageSummary(User $user): Collection
    {
        return $this->archiveVisibilityService->filterCoverageRows(
            $this->dwhCoverageRepository->getCoverageSummary(),
            $user,
        );
    }

    /**
     * @return array<int, int>
     */
    private function supportedCategoryIds(): array
    {
        return Category::query()
            ->whereIn('category_name', array_keys(config('siardi.supported_reconciliation_categories', [])))
            ->pluck('id')
            ->all();
    }
}
