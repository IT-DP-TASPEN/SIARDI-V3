<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\Category;
use App\Models\CategoryReferenceField;
use App\Repositories\DwhCoverageRepository;
use App\Support\LegacyReferenceSuggestionParser;
use App\Support\ReferenceNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ArchiveBusinessReferenceService
{
    /**
     * @var array<int, string>
     */
    private const DEPRECATED_REFERENCE_TYPES = [
        'loan_contract_no',
    ];

    public function __construct(
        private readonly DwhCoverageRepository $dwhCoverageRepository,
        private readonly LegacyReferenceSuggestionParser $legacyReferenceSuggestionParser,
    ) {}

    /**
     * @return Collection<int, CategoryReferenceField>
     */
    public function getFieldDefinitionsForCategory(?int $categoryId): Collection
    {
        if (! config('siardi.features.business_references') || blank($categoryId)) {
            return collect();
        }

        return CategoryReferenceField::query()
            ->where('category_id', $categoryId)
            ->whereNotIn('reference_type', self::DEPRECATED_REFERENCE_TYPES)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function getFormStateForArchive(Archive $archive): array
    {
        $activeFieldIds = $this->getFieldDefinitionsForCategory($archive->archive_category)
            ->pluck('id')
            ->all();

        if ($activeFieldIds === []) {
            return [];
        }

        return $archive->businessReferences()
            ->whereIn('category_reference_field_id', $activeFieldIds)
            ->pluck('raw_value', 'category_reference_field_id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function deprecatedReferenceTypes(): array
    {
        return self::DEPRECATED_REFERENCE_TYPES;
    }

    /**
     * @return Collection<int, ArchiveBusinessReference>
     */
    public function visibleBusinessReferences(Archive $archive): Collection
    {
        $archive->loadMissing('businessReferences.categoryReferenceField');

        return $archive->businessReferences
            ->filter(fn (ArchiveBusinessReference $reference): bool => ! in_array($reference->reference_type, self::DEPRECATED_REFERENCE_TYPES, true))
            ->values();
    }

    /**
     * @param  array<int|string, mixed>  $submittedReferences
     */
    public function syncForArchive(Archive $archive, array $submittedReferences): void
    {
        $fields = $this->getFieldDefinitionsForCategory($archive->archive_category)->keyBy('id');
        $activeFieldIds = $fields->keys()->all();

        $archive->businessReferences()
            ->whereNotIn('category_reference_field_id', $activeFieldIds)
            ->delete();

        foreach ($fields as $fieldId => $field) {
            $rawValue = trim((string) ($submittedReferences[$fieldId] ?? ''));

            if ($rawValue === '') {
                $archive->businessReferences()
                    ->where('category_reference_field_id', $fieldId)
                    ->delete();

                continue;
            }

            $normalizedValue = ReferenceNormalizer::normalize($rawValue, $field->normalizer ?? 'uppercase_compact');
            $match = $normalizedValue
                ? $this->dwhCoverageRepository->matchReference($field, $normalizedValue, $archive->branchOffice?->dwhMapping?->dwh_location_code)
                : null;

            ArchiveBusinessReference::query()->updateOrCreate(
                [
                    'archive_id' => $archive->id,
                    'category_reference_field_id' => $fieldId,
                ],
                [
                    'reference_type' => $field->reference_type,
                    'raw_value' => $rawValue,
                    'normalized_value' => $normalizedValue,
                    'source_system' => 'siardi',
                    'source_table' => 'archives',
                    'source_key_name' => $field->reference_type,
                    'branch_code' => $archive->branchOffice?->branch_code,
                    'matched_table' => $match['matched_table'] ?? null,
                    'matched_source_key' => $match['matched_source_key'] ?? null,
                ],
            );
        }
    }

    /**
     * @return array{archive_id: int|string, updated_references: int, matched_references: int, unmatched_references: int}
     */
    public function rematchArchive(Archive $archive): array
    {
        $archive->loadMissing([
            'branchOffice.dwhMapping',
            'businessReferences.categoryReferenceField.category',
        ]);

        $updatedReferences = 0;
        $matchedReferences = 0;
        $unmatchedReferences = 0;
        $dwhLocationCode = $archive->branchOffice?->dwhMapping?->dwh_location_code;

        foreach ($archive->businessReferences as $reference) {
            $field = $reference->categoryReferenceField;

            if (! $field || blank($reference->normalized_value)) {
                continue;
            }

            $match = $this->dwhCoverageRepository->matchReference(
                $field,
                (string) $reference->normalized_value,
                $dwhLocationCode,
            );

            $matchedTable = $match['matched_table'] ?? null;
            $matchedSourceKey = $match['matched_source_key'] ?? null;

            if ($reference->matched_table !== $matchedTable || $reference->matched_source_key !== $matchedSourceKey) {
                $reference->forceFill([
                    'matched_table' => $matchedTable,
                    'matched_source_key' => $matchedSourceKey,
                ])->save();

                $updatedReferences++;
            }

            if (filled($matchedSourceKey)) {
                $matchedReferences++;
            } else {
                $unmatchedReferences++;
            }
        }

        return [
            'archive_id' => $archive->getKey(),
            'updated_references' => $updatedReferences,
            'matched_references' => $matchedReferences,
            'unmatched_references' => $unmatchedReferences,
        ];
    }

    /**
     * @param  iterable<int, int|string>  $archiveIds
     * @return array{processed_archives: int, updated_references: int}
     */
    public function rematchArchives(iterable $archiveIds): array
    {
        $processedArchives = 0;
        $updatedReferences = 0;

        Archive::query()
            ->with([
                'branchOffice.dwhMapping',
                'businessReferences.categoryReferenceField.category',
            ])
            ->whereKey(collect($archiveIds)->filter()->values()->all())
            ->each(function (Archive $archive) use (&$processedArchives, &$updatedReferences): void {
                $result = $this->rematchArchive($archive);
                $processedArchives++;
                $updatedReferences += $result['updated_references'];
            });

        return [
            'processed_archives' => $processedArchives,
            'updated_references' => $updatedReferences,
        ];
    }

    public function rematchableArchivesQuery(bool $force = false): Builder
    {
        $supportedCategoryIds = Category::query()
            ->whereIn('category_name', array_keys(config('siardi.supported_reconciliation_categories', [])))
            ->pluck('id')
            ->all();

        return Archive::query()
            ->select('archives.id')
            ->distinct()
            ->whereIn('archives.archive_category', $supportedCategoryIds)
            ->whereHas('businessReferences', function (Builder $query) use ($force): void {
                $query->whereHas('categoryReferenceField', function (Builder $fieldQuery): void {
                    $fieldQuery->where('is_primary_match_key', true);
                });

                if (! $force) {
                    $query->whereNull('matched_source_key');
                }
            });
    }

    /**
     * @param  array<int|string, mixed>  $submittedReferences
     * @return array<string, string>
     */
    public function validateReferencesForArchive(Archive $archive, array $submittedReferences): array
    {
        $archive->loadMissing(['category', 'branchOffice.dwhMapping']);

        $fields = $this->getFieldDefinitionsForCategory($archive->archive_category)->keyBy('id');

        if ($fields->isEmpty()) {
            return [];
        }

        $primaryField = $fields->firstWhere('is_primary_match_key', true);

        if (! $primaryField) {
            return [];
        }

        $normalizedValues = $fields->mapWithKeys(function (CategoryReferenceField $field) use ($submittedReferences): array {
            $rawValue = trim((string) ($submittedReferences[$field->id] ?? ''));

            if ($rawValue === '') {
                return [$field->id => null];
            }

            return [
                $field->id => ReferenceNormalizer::normalize(
                    $rawValue,
                    $field->normalizer ?? 'uppercase_compact',
                ),
            ];
        });

        $primaryNormalizedValue = $normalizedValues->get($primaryField->id);

        if (! filled($primaryNormalizedValue)) {
            return [];
        }

        $dwhMapping = $archive->branchOffice?->dwhMapping;

        if (! ($dwhMapping?->is_active)) {
            return [
                "referenceInputs.{$primaryField->id}" => 'Cabang arsip ini belum punya mapping target aktif.',
            ];
        }

        $matchedCandidate = $this->dwhCoverageRepository->findExactReferenceCandidate(
            $primaryField,
            $primaryNormalizedValue,
            $dwhMapping->dwh_location_code,
        );

        if (! $matchedCandidate) {
            return [
                "referenceInputs.{$primaryField->id}" => 'Referensi utama tidak ditemukan pada data target cabang ini.',
            ];
        }

        $errors = [];

        foreach ($fields as $field) {
            if ($field->id === $primaryField->id) {
                continue;
            }

            $submittedNormalizedValue = $normalizedValues->get($field->id);

            if (! filled($submittedNormalizedValue)) {
                continue;
            }

            if (! array_key_exists($field->reference_type, $matchedCandidate['reference_values'])) {
                continue;
            }

            $matchedNormalizedValue = ReferenceNormalizer::normalize(
                (string) ($matchedCandidate['reference_values'][$field->reference_type] ?? ''),
                $field->normalizer ?? 'uppercase_compact',
            );

            if ($submittedNormalizedValue !== $matchedNormalizedValue) {
                $errors["referenceInputs.{$field->id}"] = "{$field->label} tidak cocok dengan data target untuk referensi utama ini.";
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function getLegacySuggestions(Archive $archive): array
    {
        return $this->legacyReferenceSuggestionParser->suggestionsForArchive($archive);
    }

    /**
     * @param  array<int|string, mixed>  $submittedReferences
     * @param  array<string, mixed>  $searchHelperInputs
     * @return Collection<int, array<string, mixed>>
     */
    public function lookupCandidatesForArchive(Archive $archive, array $submittedReferences, array $searchHelperInputs = []): Collection
    {
        $archive->loadMissing(['category', 'branchOffice.dwhMapping']);

        $filters = $this->getFieldDefinitionsForCategory($archive->archive_category)
            ->mapWithKeys(function (CategoryReferenceField $field) use ($submittedReferences): array {
                $value = ReferenceNormalizer::normalize(
                    (string) ($submittedReferences[$field->id] ?? ''),
                    $field->normalizer ?? 'uppercase_compact',
                );

                return [$field->reference_type => $value];
            })
            ->filter(fn (?string $value): bool => filled($value))
            ->merge($this->normalizeSearchHelperInputs($searchHelperInputs))
            ->all();

        if ($filters === [] || ! ($archive->category instanceof Category)) {
            return collect();
        }

        if (! ($archive->branchOffice?->dwhMapping?->is_active)) {
            return collect();
        }

        $dwhLocationCode = $archive->branchOffice?->dwhMapping?->is_active
            ? $archive->branchOffice?->dwhMapping?->dwh_location_code
            : null;

        return $this->dwhCoverageRepository->searchReferenceCandidates($archive->category, $dwhLocationCode, $filters);
    }

    /**
     * @return array{label: string, color: string, tone: string, description: string}
     */
    public function getLinkageStatus(Archive $archive): array
    {
        $archive->loadMissing(['category', 'businessReferences.categoryReferenceField']);
        $visibleReferences = $this->visibleBusinessReferences($archive);

        if (! config('siardi.features.business_references')) {
            return [
                'label' => 'Feature Nonaktif',
                'color' => 'gray',
                'tone' => 'info',
                'description' => 'Referensi bisnis belum diaktifkan pada environment ini.',
            ];
        }

        $categoryName = $archive->category?->category_name;
        $fields = $this->getFieldDefinitionsForCategory($archive->archive_category);

        if (! $this->isSupportedCategoryName($categoryName)) {
            return [
                'label' => 'Legacy Only',
                'color' => 'gray',
                'tone' => 'info',
                'description' => 'Kategori ini belum masuk scope target dan realisasi.',
            ];
        }

        if ($fields->isEmpty()) {
            return [
                'label' => 'Konfigurasi Belum Aktif',
                'color' => 'warning',
                'tone' => 'warning',
                'description' => 'Kategori ini didukung, tetapi field referensi bisnis belum dikonfigurasi.',
            ];
        }

        if ($visibleReferences->isEmpty()) {
            return [
                'label' => 'Belum Ditautkan',
                'color' => 'danger',
                'tone' => 'danger',
                'description' => 'Arsip belum memiliki referensi bisnis yang tersimpan.',
            ];
        }

        $primaryFieldId = $fields->firstWhere('is_primary_match_key', true)?->id;
        $primaryReference = $visibleReferences
            ->first(fn (ArchiveBusinessReference $reference): bool => $reference->category_reference_field_id === $primaryFieldId);

        if (! $primaryReference) {
            return [
                'label' => 'Referensi Belum Lengkap',
                'color' => 'warning',
                'tone' => 'warning',
                'description' => 'Referensi utama untuk rekonsiliasi belum diisi.',
            ];
        }

        if (filled($primaryReference->matched_source_key)) {
            return [
                'label' => 'Matched ke Data Target',
                'color' => 'success',
                'tone' => 'success',
                'description' => 'Referensi utama sudah berhasil dicocokkan dengan data target.',
            ];
        }

        return [
            'label' => 'Belum Match Data Target',
            'color' => 'warning',
            'tone' => 'warning',
            'description' => 'Referensi tersimpan, tetapi belum ada data target yang cocok.',
        ];
    }

    private function isSupportedCategoryName(?string $categoryName): bool
    {
        return filled($categoryName) && array_key_exists(
            $categoryName,
            config('siardi.supported_reconciliation_categories', []),
        );
    }

    /**
     * @param  array<string, mixed>  $searchHelperInputs
     * @return array<string, string>
     */
    private function normalizeSearchHelperInputs(array $searchHelperInputs): array
    {
        return collect($searchHelperInputs)
            ->mapWithKeys(function (mixed $value, string $key): array {
                $value = trim((string) $value);

                if ($value === '') {
                    return [$key => ''];
                }

                if ($key === 'loan_alt_account_no') {
                    return [$key => preg_replace('/\D+/', '', $value) ?: ''];
                }

                return [$key => ReferenceNormalizer::normalize($value)];
            })
            ->filter(fn (string $value): bool => filled($value))
            ->all();
    }
}
