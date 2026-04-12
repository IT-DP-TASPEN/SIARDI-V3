<?php

namespace App\Repositories;

use App\Models\ArchiveBusinessReference;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DwhCoverageRepository
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const CATEGORY_MAP = [
        'TABUNGAN' => [
            'table' => 'raw_savings',
            'business_key_column' => 'norekening',
            'cif_column' => 'nocif',
            'branch_column' => 'locationid',
            'status_column' => 'status_dokumen',
            'source_key_column' => '_row_key',
            'reference_columns' => [
                'cif' => 'nocif',
                'savings_account_no' => 'norekening',
            ],
        ],
        'KREDIT' => [
            'table' => 'raw_loans',
            'business_key_column' => 'id',
            'cif_column' => 'nocif',
            'branch_column' => 'locationid',
            'status_column' => 'status_dokumen',
            'source_key_column' => '_row_key',
            'reference_columns' => [
                'cif' => 'nocif',
                'loan_account_no' => 'id',
                'loan_contract_no' => 'noperjanjiankredit',
            ],
        ],
        'BILYET DEPOSITO' => [
            'table' => 'raw_time_deposits',
            'business_key_column' => 'nobilyet',
            'cif_column' => 'nocif',
            'branch_column' => 'locationid',
            'status_column' => 'status_dokumen',
            'source_key_column' => '_row_key',
            'reference_columns' => [
                'cif' => 'nocif',
                'deposito_bilyet_no' => 'nobilyet',
            ],
        ],
    ];

    public function getLatestSnapshotDateForCategory(Category|string $category): ?string
    {
        $definition = $this->resolveDefinition($category);

        if ($definition === null) {
            return null;
        }

        return DB::connection('dwh')->table($definition['table'])->max('as_of_date');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getCoverageSummary(?int $branchOfficeId = null, ?int $categoryId = null, ?string $asOfDate = null): Collection
    {
        $categories = Category::query()
            ->whereIn('category_name', array_keys(self::CATEGORY_MAP))
            ->when($categoryId, fn ($query) => $query->whereKey($categoryId))
            ->orderBy('category_name')
            ->get();

        $branchMappings = DwhBranchMapping::query()
            ->with('branchOffice')
            ->where('is_active', true)
            ->when($branchOfficeId, fn ($query) => $query->where('branch_office_id', $branchOfficeId))
            ->orderBy('siardi_branch_code')
            ->get()
            ->keyBy('branch_office_id');

        $realizedCounts = ArchiveBusinessReference::query()
            ->selectRaw('archives.archive_category as category_id, archives.archive_branch_office as branch_office_id, COUNT(DISTINCT archive_business_references.normalized_value) as realized_count')
            ->join('archives', 'archives.id', '=', 'archive_business_references.archive_id')
            ->join('category_reference_fields', 'category_reference_fields.id', '=', 'archive_business_references.category_reference_field_id')
            ->where('category_reference_fields.is_primary_match_key', true)
            ->whereIn('archives.archive_category', $categories->pluck('id'))
            ->when($branchOfficeId, fn ($query) => $query->where('archives.archive_branch_office', $branchOfficeId))
            ->groupBy('archives.archive_category', 'archives.archive_branch_office')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->category_id}:{$row->branch_office_id}" => (int) $row->realized_count]);

        $rows = collect();

        foreach ($categories as $category) {
            $definition = $this->resolveDefinition($category);

            if ($definition === null) {
                continue;
            }

            $resolvedAsOfDate = $asOfDate ?: $this->getLatestSnapshotDateForCategory($category);
            $targetsByLocation = $this->buildTargetQuery($category, $resolvedAsOfDate)
                ->selectRaw("{$definition['branch_column']} as dwh_location_code, COUNT(DISTINCT {$this->normalizedSql($definition['business_key_column'])}) as target_count")
                ->groupBy($definition['branch_column'])
                ->pluck('target_count', 'dwh_location_code');

            foreach ($branchMappings as $branchMapping) {
                $targetCount = (int) ($targetsByLocation[$branchMapping->dwh_location_code] ?? 0);
                $realizedCount = (int) ($realizedCounts["{$category->id}:{$branchMapping->branch_office_id}"] ?? 0);

                $rows->push([
                    'category_id' => $category->id,
                    'category_name' => $category->category_name,
                    'branch_office_id' => $branchMapping->branch_office_id,
                    'branch_name' => $branchMapping->branchOffice?->branch_name,
                    'branch_code' => $branchMapping->siardi_branch_code,
                    'dwh_location_code' => $branchMapping->dwh_location_code,
                    'as_of_date' => $resolvedAsOfDate,
                    'target_count' => $targetCount,
                    'realized_count' => $realizedCount,
                    'missing_count' => max($targetCount - $realizedCount, 0),
                    'coverage_percentage' => $targetCount > 0 ? round(($realizedCount / $targetCount) * 100, 2) : 0.0,
                ]);
            }
        }

        return $rows
            ->sortBy([
                ['category_name', 'asc'],
                ['branch_code', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getMissingRecords(int $categoryId, int $branchOfficeId, ?string $asOfDate = null): Collection
    {
        $category = Category::query()->findOrFail($categoryId);
        $definition = $this->resolveDefinition($category);
        $branchMapping = DwhBranchMapping::query()
            ->with('branchOffice')
            ->where('branch_office_id', $branchOfficeId)
            ->where('is_active', true)
            ->firstOrFail();

        $resolvedAsOfDate = $asOfDate ?: $this->getLatestSnapshotDateForCategory($category);
        $rows = $this->buildTargetQuery($category, $resolvedAsOfDate, $branchMapping->dwh_location_code)
            ->select([
                $definition['source_key_column'].' as source_key',
                $definition['branch_column'].' as branch_code',
                $definition['cif_column'].' as cif',
                $definition['business_key_column'].' as business_key',
                $definition['status_column'].' as status',
                'as_of_date',
            ])
            ->get()
            ->map(function ($row) {
                $row = (array) $row;
                $row['normalized_business_key'] = strtoupper((string) preg_replace('/\s+/', '', trim((string) $row['business_key'])));

                return $row;
            })
            ->filter(fn (array $row): bool => filled($row['normalized_business_key']))
            ->unique('normalized_business_key')
            ->values();

        $coveredKeys = ArchiveBusinessReference::query()
            ->join('archives', 'archives.id', '=', 'archive_business_references.archive_id')
            ->join('category_reference_fields', 'category_reference_fields.id', '=', 'archive_business_references.category_reference_field_id')
            ->where('archives.archive_category', $categoryId)
            ->where('archives.archive_branch_office', $branchOfficeId)
            ->where('category_reference_fields.is_primary_match_key', true)
            ->pluck('archive_business_references.normalized_value')
            ->filter()
            ->all();

        return $rows
            ->reject(fn (array $row): bool => in_array($row['normalized_business_key'], $coveredKeys, true))
            ->values();
    }

    /**
     * @return array<string, string>|null
     */
    public function matchReference(CategoryReferenceField $field, string $normalizedValue, ?string $dwhLocationCode = null): ?array
    {
        $category = $field->category;
        $definition = $category ? $this->resolveDefinition($category) : null;

        if ($definition === null) {
            return null;
        }

        $column = $definition['reference_columns'][$field->reference_type] ?? null;

        if ($column === null) {
            return null;
        }

        $match = DB::connection('dwh')
            ->table($definition['table'])
            ->where('as_of_date', $this->getLatestSnapshotDateForCategory($category))
            ->when($dwhLocationCode, fn (QueryBuilder $query) => $query->where($definition['branch_column'], $dwhLocationCode))
            ->whereRaw("{$this->normalizedSql($column)} = ?", [$normalizedValue])
            ->first([$definition['source_key_column'].' as source_key']);

        if (! $match) {
            return null;
        }

        return [
            'matched_table' => $definition['table'],
            'matched_source_key' => (string) $match->source_key,
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function searchReferenceCandidates(Category|int $category, ?string $dwhLocationCode, array $filters, int $limit = 10): Collection
    {
        $category = $category instanceof Category ? $category : Category::query()->find($category);
        $definition = $category ? $this->resolveDefinition($category) : null;

        if (! $category || $definition === null) {
            return collect();
        }

        $filters = collect($filters)
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => strtoupper((string) preg_replace('/\s+/', '', trim((string) $value))))
            ->all();

        if ($filters === []) {
            return collect();
        }

        $asOfDate = $this->getLatestSnapshotDateForCategory($category);

        if (! $asOfDate) {
            return collect();
        }

        $baseQuery = $this->buildCandidateQuery($category, $asOfDate, $dwhLocationCode);
        $exactMatches = $this->mapCandidateRows(
            $this->applyCandidateFilters(clone $baseQuery, $definition, $filters)
                ->limit($limit)
                ->get(),
            $definition,
        );

        if ($exactMatches->isNotEmpty()) {
            return $exactMatches;
        }

        $primaryReferenceType = $category->referenceFields()
            ->where('is_primary_match_key', true)
            ->value('reference_type');

        $primaryValue = $primaryReferenceType ? ($filters[$primaryReferenceType] ?? null) : null;

        if (! $primaryValue || strlen($primaryValue) < 6) {
            return collect();
        }

        return $this->mapCandidateRows(
            $this->applyCandidateFilters(clone $baseQuery, $definition, $filters, true, $primaryReferenceType)
                ->limit($limit)
                ->get(),
            $definition,
        );
    }

    /**
     * @return Collection<int, string>
     */
    public function getAvailableSnapshotDatesForCategory(int $categoryId, int $limit = 30): Collection
    {
        $category = Category::query()->find($categoryId);
        $definition = $category ? $this->resolveDefinition($category) : null;

        if ($definition === null) {
            return collect();
        }

        return DB::connection('dwh')
            ->table($definition['table'])
            ->select('as_of_date')
            ->distinct()
            ->orderByDesc('as_of_date')
            ->limit($limit)
            ->pluck('as_of_date');
    }

    private function buildCandidateQuery(Category $category, ?string $asOfDate, ?string $dwhLocationCode = null): QueryBuilder
    {
        $definition = $this->resolveDefinition($category);
        $query = $this->buildTargetQuery($category, $asOfDate, $dwhLocationCode)
            ->select($this->candidateSelects($definition))
            ->orderBy($definition['business_key_column']);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $filters
     */
    private function applyCandidateFilters(
        QueryBuilder $query,
        array $definition,
        array $filters,
        bool $allowPrimaryPrefixFallback = false,
        ?string $primaryReferenceType = null,
    ): QueryBuilder {
        foreach ($filters as $referenceType => $value) {
            $column = $definition['reference_columns'][$referenceType] ?? null;

            if (! $column) {
                continue;
            }

            $operator = $allowPrimaryPrefixFallback && $referenceType === $primaryReferenceType ? 'like' : '=';
            $comparisonValue = $operator === 'like' ? $value.'%' : $value;

            $query->whereRaw("{$this->normalizedSql($column)} {$operator} ?", [$comparisonValue]);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function candidateSelects(array $definition): array
    {
        $selects = [
            $definition['source_key_column'].' as source_key',
            'as_of_date',
            $definition['branch_column'].' as dwh_location_code',
            $definition['cif_column'].' as cif',
            $definition['business_key_column'].' as business_key',
            $definition['status_column'].' as status',
        ];

        foreach ($definition['reference_columns'] as $referenceType => $column) {
            $selects[] = $column.' as '.$referenceType;
        }

        return $selects;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return Collection<int, array<string, mixed>>
     */
    private function mapCandidateRows(Collection $rows, array $definition): Collection
    {
        return $rows
            ->map(function ($row) use ($definition): array {
                $data = (array) $row;

                return [
                    'source_key' => (string) ($data['source_key'] ?? ''),
                    'as_of_date' => (string) ($data['as_of_date'] ?? ''),
                    'dwh_location_code' => (string) ($data['dwh_location_code'] ?? ''),
                    'cif' => (string) ($data['cif'] ?? ''),
                    'business_key' => (string) ($data['business_key'] ?? ''),
                    'status' => (string) ($data['status'] ?? ''),
                    'reference_values' => collect(array_keys($definition['reference_columns']))
                        ->mapWithKeys(fn (string $referenceType): array => [$referenceType => (string) ($data[$referenceType] ?? '')])
                        ->all(),
                ];
            })
            ->filter(fn (array $row): bool => filled($row['source_key']))
            ->unique('source_key')
            ->values();
    }

    private function buildTargetQuery(Category $category, ?string $asOfDate, ?string $dwhLocationCode = null): QueryBuilder
    {
        $definition = $this->resolveDefinition($category);
        $query = DB::connection('dwh')->table($definition['table'])->where('as_of_date', $asOfDate);

        if ($dwhLocationCode !== null) {
            $query->where($definition['branch_column'], $dwhLocationCode);
        }

        return match ($category->category_name) {
            'TABUNGAN' => $query
                ->where($definition['branch_column'], '!=', '000')
                ->where($definition['status_column'], '!=', 'Closed'),
            'BILYET DEPOSITO' => $query
                ->where($definition['status_column'], '!=', 'Sudah Dicairkan'),
            default => $query,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDefinition(Category|string $category): ?array
    {
        $categoryName = $category instanceof Category ? $category->category_name : $category;

        return self::CATEGORY_MAP[$categoryName] ?? null;
    }

    private function normalizedSql(string $column): string
    {
        return "UPPER(REPLACE(TRIM({$column}), ' ', ''))";
    }
}
