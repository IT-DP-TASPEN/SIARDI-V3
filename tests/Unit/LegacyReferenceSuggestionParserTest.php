<?php

namespace Tests\Unit;

use App\Models\Archive;
use App\Support\LegacyReferenceSuggestionParser;
use Tests\TestCase;

class LegacyReferenceSuggestionParserTest extends TestCase
{
    public function test_it_extracts_kredit_alt_rekening_with_or_without_dots(): void
    {
        $parser = app(LegacyReferenceSuggestionParser::class);

        $dottedArchive = new Archive([
            'archive_code' => 'KRD-01',
            'archive_name' => 'Debitur 12.345.67890',
            'archive_path' => 'archives/12.345.67890 - kredit.pdf',
        ]);

        $plainArchive = new Archive([
            'archive_code' => 'KRD-02',
            'archive_name' => 'Debitur 1234567890',
            'archive_path' => 'archives/1234567890 - kredit.pdf',
        ]);

        $dottedSuggestions = $parser->suggestionsForArchive($dottedArchive);
        $plainSuggestions = $parser->suggestionsForArchive($plainArchive);

        $this->assertSame('1234567890', $dottedSuggestions['loan_alt_account_no']);
        $this->assertArrayNotHasKey('loan_contract_no', $dottedSuggestions);
        $this->assertSame('1234567890', $plainSuggestions['loan_alt_account_no']);
        $this->assertArrayNotHasKey('loan_contract_no', $plainSuggestions);
    }

    public function test_it_extracts_tabungan_alt_rekening_with_or_without_dots(): void
    {
        $parser = app(LegacyReferenceSuggestionParser::class);

        $dottedArchive = new Archive([
            'archive_code' => 'TBG-01',
            'archive_name' => 'Nasabah 99.887.76655',
            'archive_path' => 'archives/99.887.76655 - tabungan.pdf',
        ]);

        $plainArchive = new Archive([
            'archive_code' => 'TBG-02',
            'archive_name' => 'Nasabah 9988776655',
            'archive_path' => 'archives/9988776655 - tabungan.pdf',
        ]);

        $dottedSuggestions = $parser->suggestionsForArchive($dottedArchive);
        $plainSuggestions = $parser->suggestionsForArchive($plainArchive);

        $this->assertSame('9988776655', $dottedSuggestions['savings_alt_account_no']);
        $this->assertSame('9988776655', $plainSuggestions['savings_alt_account_no']);
    }

    public function test_it_does_not_extract_alt_rekening_from_long_primary_account_numbers(): void
    {
        $parser = app(LegacyReferenceSuggestionParser::class);

        $archive = new Archive([
            'archive_name' => 'Rekening 001000000000001 kredit 1234567890123456',
            'archive_path' => 'archives/001000000000001.pdf',
        ]);

        $suggestions = $parser->suggestionsForArchive($archive);

        $this->assertSame('', $suggestions['savings_alt_account_no']);
        $this->assertSame('', $suggestions['loan_alt_account_no']);
    }
}
