<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Services\ArchiveVisibilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RekapArsipController extends Controller
{
    public function print()
    {
        Gate::authorize('page_RekapanArsip');

        $user = auth()->user();
        $visibility = app(ArchiveVisibilityService::class);
        $categoryIds = Category::query()
            ->whereIn('category_name', ['TABUNGAN', 'BILYET DEPOSITO', 'KREDIT'])
            ->pluck('id', 'category_name');

        $counts = $visibility->applyArchiveScope(
            Archive::query()
                ->selectRaw('archive_branch_office, archive_category, COUNT(*) as total')
                ->whereIn('archive_category', $categoryIds->values()->all()),
            $user,
        )
            ->groupBy('archive_branch_office', 'archive_category')
            ->get()
            ->groupBy('archive_branch_office');

        $branchQuery = BranchOffice::query()
            ->whereNotIn('branch_code', ['00', '09']);

        if (! $visibility->canViewAllBranches($user)) {
            $branchQuery->whereIn('id', $visibility->visibleBranchOfficeIds($user));
        }

        $rekap = $branchQuery
            ->get()
            ->mapWithKeys(function (BranchOffice $branch) use ($counts, $categoryIds): array {
                $branchName = $this->formatBranchName($branch->branch_name);
                $branchCounts = collect($counts->get($branch->id, []))->pluck('total', 'archive_category');
                $tabunganCount = (int) ($branchCounts[$categoryIds['TABUNGAN'] ?? 0] ?? 0);
                $depositoCount = (int) ($branchCounts[$categoryIds['BILYET DEPOSITO'] ?? 0] ?? 0);
                $kreditCount = (int) ($branchCounts[$categoryIds['KREDIT'] ?? 0] ?? 0);

                return [
                    $branchName => [
                        'tabungan' => $tabunganCount,
                        'deposito' => $depositoCount,
                        'kredit' => $kreditCount,
                        'total' => $tabunganCount + $depositoCount + $kreditCount,
                    ],
                ];
            });

        return view('filament.pages.print-rekap-arsip', compact('rekap'));
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
