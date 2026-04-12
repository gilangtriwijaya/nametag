<?php

namespace Tests\Unit\Services\Nametag;

use App\Services\Nametag\NametagTextLayout;
use PHPUnit\Framework\TestCase;

class NametagTextLayoutGelarPreservationTest extends TestCase
{
    /**
     * Create a test helper class that uses NametagTextLayout trait
     * so we can access and test the applyCase() method
     */
    private function createTester()
    {
        return new class {
            use NametagTextLayout;

            public function testApplyCase($text, $mode)
            {
                return $this->applyCase($text, $mode);
            }
        };
    }

    /**
     * Test that applyCase('title') preserves gelar (after comma) unchanged.
     * This ensures quote-escaped gelar like S.IP, S.IKON stays uppercase.
     */
    public function test_applyCaseTitlePreservesGelarAfterComma()
    {
        $tester = $this->createTester();

        // Test case: Name with gelar "IP" that was quote-escaped to preserve uppercase
        $input = 'AGUSTA IRNANDA, S.IP';
        $expected = 'Agusta Irnanda, S.IP';  // Name title-cased, gelar preserved
        $result = $tester->testApplyCase($input, 'title');

        $this->assertEquals($expected, $result,
            "Name should be title-cased but gelar S.IP should remain S.IP (not S.Ip)"
        );
    }

    /**
     * Test case 2: Multiple uppercase letters in gelar
     */
    public function test_applyCaseTitlePreservesMultipleLetterGelar()
    {
        $tester = $this->createTester();

        $input = 'SRI WIBOWO, S.SKM';
        $expected = 'Sri Wibowo, S.SKM';  // Gelar preserved as uppercase
        $result = $tester->testApplyCase($input, 'title');

        $this->assertEquals($expected, $result,
            "Gelar S.SKM should stay S.SKM, not become S.Skm"
        );
    }

    /**
     * Test case 3: Multiple gelar degrees
     */
    public function test_applyCaseTitlePreservesMultipleDegrees()
    {
        $tester = $this->createTester();

        $input = 'JOHANNES SOEMANTO, S.ED., M.TECH';
        $expected = 'Johannes Soemanto, S.ED., M.TECH';
        $result = $tester->testApplyCase($input, 'title');

        $this->assertEquals($expected, $result,
            "Multiple gelar should be preserved unchanged"
        );
    }

    /**
     * Test case 4: No gelar - should still apply title case normally
     */
    public function test_applyCaseTitleWorksWithoutGelar()
    {
        $tester = $this->createTester();

        $input = 'AGUSTA IRNANDA';
        $expected = 'Agusta Irnanda';
        $result = $tester->testApplyCase($input, 'title');

        $this->assertEquals($expected, $result,
            "Without gelar, name should still be title-cased normally"
        );
    }

    /**
     * Test case 5: Edge case - gelar with spaces (multiple degrees)
     */
    public function test_applyCaseTitlePreservesGelarWithSpaces()
    {
        $tester = $this->createTester();

        $input = 'MADE SUARTA, S.H., M.H.';
        $expected = 'Made Suarta, S.H., M.H.';
        $result = $tester->testApplyCase($input, 'title');

        $this->assertEquals($expected, $result,
            "Gelar with multiple spaces should be preserved as-is"
        );
    }

    /**
     * Test case 6: Gelar with complex abbreviations that need title-casing in name
     */
    public function test_applyCaseTitleHandlesComplexNames()
    {
        $tester = $this->createTester();

        $input = 'AHMAD KOMARUDIN SYAIFUL, S.M.';
        $expected = 'Ahmad Komarudin Syaiful, S.M.';
        $result = $tester->testApplyCase($input, 'title');

        $this->assertEquals($expected, $result,
            "Complex name with multiple parts should be title-cased while preserving gelar"
        );
    }

    /**
     * Test case 7: Upper case mode should uppercase entire string including gelar
     */
    public function test_applyCaseUpperAppliesEverywhere()
    {
        $tester = $this->createTester();

        $input = 'Ahmad Komarudin, s.m.';
        $expected = 'AHMAD KOMARUDIN, S.M.';
        $result = $tester->testApplyCase($input, 'upper');

        $this->assertEquals($expected, $result,
            "Upper case should apply to entire string including gelar"
        );
    }

    /**
     * Test case 8: Lower case mode should lowercase entire string
     */
    public function test_applyCaseLowerAppliesEverywhere()
    {
        $tester = $this->createTester();

        $input = 'AHMAD KOMARUDIN, S.M.';
        $expected = 'ahmad komarudin, s.m.';
        $result = $tester->testApplyCase($input, 'lower');

        $this->assertEquals($expected, $result,
            "Lower case should apply to entire string including gelar"
        );
    }

    /**
     * Test case 9: None mode should return unchanged
     */
    public function test_applyCaseNonePreservesExactly()
    {
        $tester = $this->createTester();

        $input = 'AHMAD Komarudin, S.m.';
        $expected = 'AHMAD Komarudin, S.m.';
        $result = $tester->testApplyCase($input, 'none');

        $this->assertEquals($expected, $result,
            "None mode should preserve exact input"
        );
    }
}
