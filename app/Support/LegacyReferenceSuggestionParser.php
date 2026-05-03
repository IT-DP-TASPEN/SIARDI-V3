<?php

namespace App\Support;

use App\Models\Archive;

class LegacyReferenceSuggestionParser
{
    /**
     * @return array<string, string>
     */
    public function suggestionsForArchive(Archive $archive): array
    {
        $haystack = strtoupper(implode(' ', array_filter([
            $archive->archive_code,
            $archive->archive_name,
            $archive->archive_path,
            $archive->archive_description,
        ])));

        preg_match_all('/[A-Z]{2}\d{6}|\d{6,18}/', $haystack, $matches);
        $tokens = collect($matches[0] ?? [])->unique()->values();
        preg_match_all('/(?<!\d)\d{2}\.?\d{3}\.?\d{5}(?!\d)/', $haystack, $altMatches);
        $altAccountCandidates = collect($altMatches[0] ?? [])
            ->map(fn (string $token): string => preg_replace('/\D+/', '', $token) ?: '')
            ->merge($tokens->filter(fn (string $token): bool => preg_match('/^\d{10}$/', $token) === 1))
            ->filter()
            ->unique()
            ->values();

        return [
            'cif' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{11,12}$/', $token) === 1),
            'savings_account_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{12,18}$/', $token) === 1),
            'savings_alt_account_no' => (string) $altAccountCandidates->first(),
            'loan_account_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{16}$/', $token) === 1),
            'loan_alt_account_no' => (string) $altAccountCandidates->first(),
            'deposito_bilyet_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^(?:[A-Z]{2}\d{6}|\d{6})$/', $token) === 1),
        ];
    }
}
