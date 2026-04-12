<?php

namespace Tests\Unit\Support;

use App\Support\NametagData;
use Tests\TestCase;

class NametagDataTest extends TestCase
{
    /**
     * Test normalizeGelar with standard cases (no quote escape)
     */
    public function test_normalize_gelar_standard_cases(): void
    {
        // Single letters per word
        $this->assertEquals('S.T', $this->callNormalizeGelar('S.T'));
        $this->assertEquals('S.P', $this->callNormalizeGelar('S.P'));
        
        // Multi-letter per word
        $this->assertEquals('S.Psi', $this->callNormalizeGelar('S.Psi'));
        $this->assertEquals('S.Psi', $this->callNormalizeGelar('S.PSI'));  // Normalize lowercase
        $this->assertEquals('M.Kom', $this->callNormalizeGelar('M.KOM'));
        $this->assertEquals('S.I.Kom', $this->callNormalizeGelar('S.I.KOM'));
        
        // With trailing period and spaces
        $this->assertEquals('M.Kom.', $this->callNormalizeGelar('M.KOM.'));
        
        // Comma-separated degrees
        $this->assertEquals('S.Psi, M.Kom', $this->callNormalizeGelar('S.PSI, M.KOM'));
        $this->assertEquals('S.I.P, M.Kom.', $this->callNormalizeGelar('S.I.P, M.KOM.'));
    }

    /**
     * Test normalizeGelar with quote escape
     * NOTE: Quotes preserve content exactly as-is, removing the quote markers
     */
    public function test_normalize_gelar_with_quote_escape(): void
    {
        // Simple quote escape - content preserved exactly, quotes removed
        $this->assertEquals('S.IP', $this->callNormalizeGelar('S."IP"'));
        $this->assertEquals('S.IP', $this->callNormalizeGelar('S."IP"'));
        $this->assertEquals('M.KOM', $this->callNormalizeGelar('M."KOM"'));
        
        // Multiple quotes in one string
        $this->assertEquals('S.Tr.IP', $this->callNormalizeGelar('S."Tr"."IP"'));
        $this->assertEquals('S.I.P', $this->callNormalizeGelar('S.I."P"'));
        
        // Quote with normal cases mixed (non-quoted parts get title-cased)
        $this->assertEquals('S.Psi, M.KOM', $this->callNormalizeGelar('S.Psi, M."KOM"'));
        $this->assertEquals('S.I.P, M.Kom', $this->callNormalizeGelar('S.I."P", M.Kom'));
        
        // Quote with special capitalization
        $this->assertEquals('D.R', $this->callNormalizeGelar('D."R"'));
        $this->assertEquals('D.R.S.Honoris Causa', $this->callNormalizeGelar('D.R.S."Honoris Causa"'));
    }

    /**
     * Test edge cases
     */
    public function test_normalize_gelar_edge_cases(): void
    {
        // Empty string
        $this->assertEquals('', $this->callNormalizeGelar(''));
        
        // Whitespace only
        $this->assertEquals('', $this->callNormalizeGelar('   '));
        
        // Only dots
        $this->assertEquals('...', $this->callNormalizeGelar('...'));
        
        // Quote at start - gets preserved, then normalized segment gets title-cased
        $this->assertEquals('IP.S', $this->callNormalizeGelar('"IP".S'));
        
        // Incomplete quote - treated as literal characters, unmatched " + IP segment gets normalized
        // The " is not a letter so stays, but IP part gets lowercased then title-cased
        $this->assertEquals('S."ip', $this->callNormalizeGelar('S."IP'));
    }

    /**
     * Test realistic degree combinations
     */
    public function test_normalize_gelar_realistic_combinations(): void
    {
        // Indonesian standard degrees (without quotes - get title-cased)
        $this->assertEquals('S.T', $this->callNormalizeGelar('S.T'));  // Teknik
        $this->assertEquals('S.Psi', $this->callNormalizeGelar('S.Psi'));  // Psikologi
        $this->assertEquals('S.I.P', $this->callNormalizeGelar('S.I.P'));  // Ilmu Pemerintahan
        $this->assertEquals('S.I.Kom', $this->callNormalizeGelar('S.I.Kom'));  // Ilmu Komunikasi
        $this->assertEquals('S.Tr.Ip', $this->callNormalizeGelar('S.Tr.IP'));  // Sarjana Terapan Ilmu Pemerintahan (IP gets title-cased to Ip)
        
        // With quote escapes for non-standard cases (preserved exactly as-is)
        $this->assertEquals('S.IP', $this->callNormalizeGelar('S."IP"'));  // If user wants compressed format
        $this->assertEquals('S.Tr.IP', $this->callNormalizeGelar('S."Tr"."IP"'));  // Both parts quoted
        
        // Master degrees
        $this->assertEquals('M.Kom', $this->callNormalizeGelar('M.Kom'));
        $this->assertEquals('M.Si', $this->callNormalizeGelar('M.Si'));
        $this->assertEquals('M.M', $this->callNormalizeGelar('M."M"'));  // With quote
        
        // Doctorate
        $this->assertEquals('Dr', $this->callNormalizeGelar('Dr'));
        $this->assertEquals('Dr.', $this->callNormalizeGelar('Dr.'));
        $this->assertEquals('Prof.Dr', $this->callNormalizeGelar('Prof.Dr'));
        $this->assertEquals('Prof.Dr.', $this->callNormalizeGelar('Prof.Dr.'));
    }

    /**
     * Helper to call public method normalizeGelarPublic
     */
    private function callNormalizeGelar(string $input): string
    {
        return NametagData::normalizeGelarPublic($input);
    }
}
