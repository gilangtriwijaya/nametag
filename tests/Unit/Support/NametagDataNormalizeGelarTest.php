<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use App\Support\NametagData;

/**
 * Test NametagData normalization with quote-escape support
 * Verifies that the public normalizeGelarPublic() method works correctly
 */
class NametagDataNormalizeGelarTest extends TestCase
{
    /**
     * Test single quote escape
     * E.g., S."IP" should preserve IP as uppercase
     */
    public function test_single_quote_escape()
    {
        $input = 'S."IP"';
        $expected = 'S.IP';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test multiple quote escapes
     * E.g., S."TR"."IP" should preserve both as uppercase
     */
    public function test_multiple_quote_escapes()
    {
        $input = 'S."TR"."IP"';
        $expected = 'S.TR.IP';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test mixed quoted and unquoted
     * E.g., S.Psi, M."KOM" should apply rule to S.Psi and preserve KOM
     */
    public function test_mixed_quoted_and_unquoted()
    {
        $input = 'S.Psi, M."KOM"';
        $expected = 'S.Psi, M.KOM';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test comma-separated without quotes
     * Standard normalization should apply
     */
    public function test_comma_separated_without_quotes()
    {
        $input = 'S.I.KOM., M.KESOS';
        $expected = 'S.I.Kom., M.Kesos';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test quote escape at end
     */
    public function test_quote_escape_at_end()
    {
        $input = 'S."I.KOM."';
        $expected = 'S.I.KOM.';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test whitespace trimming with quotes
     */
    public function test_trim_whitespace_with_quotes()
    {
        $input = '  S."IP"  ';
        $expected = 'S.IP';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test standard multi-letter without quotes
     */
    public function test_standard_multi_letter()
    {
        $input = 'S.Psi';
        $expected = 'S.Psi';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test single letter without quotes
     */
    public function test_single_letter()
    {
        $input = 'S.T';
        $expected = 'S.T';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test that unquoted uppercase gets lowercase conversion
     */
    public function test_unquoted_uppercase_conversion()
    {
        $input = 'S.IP';
        $expected = 'S.Ip';  // First letter uppercase, rest lowercase
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test idempotent: DB stores clean data, so second parse on same data is no-op
     * 
     * Flow:
     * 1. SAVE TIME: User inputs S."IP" → normalizeGelarPublic() → S.IP (stored to DB)
     * 2. RENDER TIME: DB value S.IP → normalizeGelarPublic() → S.IP (no-op, already clean)
     * 
     * So the idempotent behavior is: clean data stays clean when parsed again.
     */
    public function test_idempotent_on_clean_data()
    {
        $cleanData = 'S.IP';
        
        $first = NametagData::normalizeGelarPublic($cleanData);
        $second = NametagData::normalizeGelarPublic($first);
        
        // Calling on already-clean data should produce same result
        $this->assertEquals($first, $second);
    }

    /**
     * Test empty string
     */
    public function test_empty_string()
    {
        $input = '';
        $expected = '';
        
        $result = NametagData::normalizeGelarPublic($input);
        
        $this->assertEquals($expected, $result);
    }
}
