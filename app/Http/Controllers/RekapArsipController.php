<?php

namespace App\Http\Controllers;

use App\Models\BranchOffice;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RekapArsipController extends Controller
{
    public function print()
    {
        $categoryIds = Category::query()
            ->whereIn('category_name', ['TABUNGAN', 'BILYET DEPOSITO', 'KREDIT'])
            ->pluck('id', 'category_name');

        $rekap = BranchOffice::query()
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
