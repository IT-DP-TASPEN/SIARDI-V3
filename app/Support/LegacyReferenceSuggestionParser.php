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
        ])));

        preg_match_all('/[A-Z]{2}\d{6}|\d{6,18}/', $haystack, $matches);
        $tokens = collect($matches[0] ?? [])->unique()->values();

        return [
            'cif' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{11,12}$/', $token) === 1),
            'savings_account_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{12,18}$/', $token) === 1),
            'loan_account_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{16}$/', $token) === 1),
            'loan_contract_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^\d{6,12}$/', $token) === 1),
            'deposito_bilyet_no' => (string) $tokens->first(fn (string $token): bool => preg_match('/^(?:[A-Z]{2}\d{6}|\d{6})$/', $token) === 1),
        ];
    }
}
