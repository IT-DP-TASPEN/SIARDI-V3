<?php

namespace App\Repositories;

use App\Models\ArchiveBusinessReference;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Models\DwhBranchMapping;
use App\Support\ReferenceNormalizer;
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
            'alternate_business_key_column' => 'noalt',
            'cif_column' => 'nocif',
            'branch_column' => 'locationid',
            'status_column' => 'status_dokumen',
            'source_key_column' => '_row_key',
            'reference_columns' => [
                'cif' => 'nocif',
                'savings_account_no' => 'norekening',
                'savings_alt_account_no' => 'noalt',
            ],
        ],
        'KREDIT' => [
            'table' => 'raw_loans',
            'business_key_column' => 'id',
            'alternate_business_key_column' => 'noalt',
            'cif_column' => 'nocif',
            'branch_column' => 'locationid',
            'status_column' => 'status_dokumen',
            'source_key_column' => '_row_key',
            'reference_columns' => [
                'cif' => 'nocif',
                'loan_account_no' => 'id',
                'loan_alt_account_no' => 'noalt',
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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getCoverageSummary(?int $branchOfficeId = null, ?int $categoryId = null): Collection
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

            $targetsByLocation = $this->buildTargetQuery($category)
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
    public function getMissingRecords(int $categoryId, int $branchOfficeId): Collection
    {
        $category = Category::query()->findOrFail($categoryId);
        $definition = $this->resolveDefinition($category);
        $branchMapping = DwhBranchMapping::query()
            ->with('branchOffice')
            ->where('branch_office_id', $branchOfficeId)
            ->where('is_active', true)
            ->firstOrFail();

        $rows = $this->buildTargetQuery($category, $branchMapping->dwh_location_code)
            ->select([
                $definition['source_key_column'].' as source_key',
                $definition['branch_column'].' as branch_code',
                $definition['cif_column'].' as cif',
                $definition['business_key_column'].' as business_key',
                $definition['status_column'].' as status',
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
     * @return array{matched_table: string, matched_source_key: string, canonical_raw_value: string, canonical_normalized_value: string, matched_via_alternate: bool}|null
     */
    public function resolveCanonicalPrimaryReferenceMatch(
        CategoryReferenceField $field,
        string $normalizedValue,
        ?string $dwhLocationCode = null,
    ): ?array {
        if (! $field->is_primary_match_key) {
            return null;
        }

        $category = $field->category;
        $definition = $category ? $this->resolveDefinition($category) : null;

        if ($definition === null) {
            return null;
        }

        $primaryColumn = $definition['reference_columns'][$field->reference_type] ?? null;

        if ($primaryColumn === null || $primaryColumn !== $definition['business_key_column']) {
            return null;
        }

        $directMatch = $this->findCanonicalBusinessKeyMatch(
            $definition,
            $field,
            $primaryColumn,
            $normalizedValue,
            $dwhLocationCode,
        );

        if ($directMatch) {
            return $directMatch + ['matched_via_alternate' => false];
        }

        $alternateColumn = $definition['alternate_business_key_column'] ?? null;
        $alternateLookupValue = preg_replace('/\D+/', '', $normalizedValue) ?: '';

        if ($alternateColumn === null || $alternateLookupValue === '') {
            return null;
        }

        $alternateMatch = $this->findCanonicalBusinessKeyMatch(
            $definition,
            $field,
            $alternateColumn,
            $alternateLookupValue,
            $dwhLocationCode,
        );

        if (! $alternateMatch) {
            return null;
        }

        return $alternateMatch + ['matched_via_alternate' => true];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findExactReferenceCandidate(CategoryReferenceField $field, string $normalizedValue, ?string $dwhLocationCode = null): ?array
    {
        $category = $field->category;
        $definition = $category ? $this->resolveDefinition($category) : null;

        if (! $category || $definition === null) {
            return null;
        }

        $column = $definition['reference_columns'][$field->reference_type] ?? null;

        if ($column === null) {
            return null;
        }

        return $this->mapCandidateRows(
            $this->buildCandidateQuery($category, $dwhLocationCode)
                ->whereRaw("{$this->normalizedSql($column)} = ?", [$normalizedValue])
                ->limit(1)
                ->get(),
            $definition,
        )->first();
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
            ->mapWithKeys(fn (mixed $value, string $referenceType): array => [
                $referenceType => $this->normalizeLookupValue($referenceType, $value),
            ])
            ->filter(fn (string $value): bool => filled($value))
            ->all();

        if ($filters === []) {
            return collect();
        }

        $baseQuery = $this->buildCandidateQuery($category, $dwhLocationCode);
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

    private function buildCandidateQuery(Category $category, ?string $dwhLocationCode = null): QueryBuilder
    {
        $definition = $this->resolveDefinition($category);
        $query = $this->buildTargetQuery($category, $dwhLocationCode)
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

    private function buildTargetQuery(Category $category, ?string $dwhLocationCode = null): QueryBuilder
    {
        $definition = $this->resolveDefinition($category);
        $query = DB::connection('dwh')->table($definition['table']);

        if ($dwhLocationCode !== null) {
            $query->where($definition['branch_column'], $dwhLocationCode);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDefinition(Category|string $category): ?array
    {
        $categoryName = $category instanceof Category ? $category->category_name : $category;

        return self::CATEGORY_MAP[$categoryName] ?? null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{matched_table: string, matched_source_key: string, canonical_raw_value: string, canonical_normalized_value: string}|null
     */
    private function findCanonicalBusinessKeyMatch(
        array $definition,
        CategoryReferenceField $field,
        string $lookupColumn,
        string $lookupValue,
        ?string $dwhLocationCode = null,
    ): ?array {
        $match = DB::connection('dwh')
            ->table($definition['table'])
            ->when($dwhLocationCode, fn (QueryBuilder $query) => $query->where($definition['branch_column'], $dwhLocationCode))
            ->whereRaw("{$this->normalizedSql($lookupColumn)} = ?", [$lookupValue])
            ->first([
                $definition['source_key_column'].' as source_key',
                $definition['business_key_column'].' as business_key',
            ]);

        if (! $match) {
            return null;
        }

        $canonicalRawValue = trim((string) ($match->business_key ?? ''));
        $canonicalNormalizedValue = ReferenceNormalizer::normalize(
            $canonicalRawValue,
            $field->normalizer ?? 'uppercase_compact',
        );

        if ($canonicalRawValue === '' || ! filled($canonicalNormalizedValue)) {
            return null;
        }

        return [
            'matched_table' => $definition['table'],
            'matched_source_key' => (string) $match->source_key,
            'canonical_raw_value' => $canonicalRawValue,
            'canonical_normalized_value' => $canonicalNormalizedValue,
        ];
    }

    private function normalizedSql(string $column): string
    {
        return "UPPER(REPLACE(TRIM({$column}), ' ', ''))";
    }

    private function normalizeLookupValue(string $referenceType, mixed $value): string
    {
        $value = trim((string) $value);

        if (in_array($referenceType, ['loan_alt_account_no', 'savings_alt_account_no'], true)) {
            return preg_replace('/\D+/', '', $value) ?: '';
        }

        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }
}
