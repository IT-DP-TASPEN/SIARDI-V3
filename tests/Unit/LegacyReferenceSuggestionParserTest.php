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
}
