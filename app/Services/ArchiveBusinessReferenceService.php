<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\ArchiveBusinessReference;
use App\Models\CategoryReferenceField;
use App\Repositories\DwhCoverageRepository;
use App\Support\LegacyReferenceSuggestionParser;
use App\Support\ReferenceNormalizer;
use Illuminate\Support\Collection;

class ArchiveBusinessReferenceService
{
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
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function getFormStateForArchive(Archive $archive): array
    {
        return $archive->businessReferences()
            ->pluck('raw_value', 'category_reference_field_id')
            ->all();
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
     * @return array<string, string>
     */
    public function getLegacySuggestions(Archive $archive): array
    {
        return $this->legacyReferenceSuggestionParser->suggestionsForArchive($archive);
    }
}
