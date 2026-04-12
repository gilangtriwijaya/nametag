<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Integration test: Verify that gelar rendering applies case correctly
 * when gelar portion (after comma) is preserved
 */
class NametagGelarRenderingTest extends TestCase
{
    /**
     * Test: applyCase('title') preserves gelar after comma
     * This tests the key fix: gelar with quote-escape should not be title-cased
     */
    public function test_apply_case_title_preserves_gelar_in_nametag()
    {
        $renderer = new class {
            use \App\Services\Nametag\NametagTextLayout;

            public function testApplyCase($text, $mode) {
                return $this->applyCase($text, $mode);
            }
        };

        // Test case 1: Name with gelar "IP" - most important for nametag
        $result = $renderer->testApplyCase('AGUSTA IRNANDA, S.IP', 'title');
        $this->assertEquals('Agusta Irnanda, S.IP', $result,
            'applyCase(title) should title-case name but preserve gelar S.IP unchanged'
        );

        // Test case 2: Multiple uppercase gelar
        $result = $renderer->testApplyCase('SRI WIBOWO, S.SKM', 'title');
        $this->assertEquals('Sri Wibowo, S.SKM', $result,
            'Gelar S.SKM should stay unchanged (not become S.Skm)'
        );

        // Test case 3: Multiple degrees
        $result = $renderer->testApplyCase('JOHANNES SOEMANTO, S.ED., M.TECH', 'title');
        $this->assertEquals('Johannes Soemanto, S.ED., M.TECH', $result,
            'Multiple gelar degrees should be preserved unchanged'
        );

        // Test case 4: Without gelar - normal title-case should still work
        $result = $renderer->testApplyCase('AGUSTA IRNANDA', 'title');
        $this->assertEquals('Agusta Irnanda', $result,
            'Without gelar, regular title-casing should apply'
        );
    }

    /**
     * Test: normalizeGelarPublic removes quotes but preserves content case
     */
    public function test_normalize_gelar_public_preserves_quote_escaped_content()
    {
        // Test normalizeGelarPublic directly
        $result = \App\Support\NametagData::normalizeGelarPublic('S."IP"');
        $this->assertEquals('S.IP', $result,
            'Quote-escaped S."IP" should become S.IP (quotes removed, content preserved)'
        );

        $result = \App\Support\NametagData::normalizeGelarPublic('S."Tr"."IP"');
        $this->assertEquals('S.Tr.IP', $result,
            'Multiple quoted segments should preserve case exactly'
        );

        // Without quotes, multi-letter segments get title-cased
        $result = \App\Support\NametagData::normalizeGelarPublic('S.IP');
        $this->assertEquals('S.Ip', $result,
            'Non-quoted S.IP gets title-cased to S.Ip (this is why quote-escape is needed)'
        );
    }
}
