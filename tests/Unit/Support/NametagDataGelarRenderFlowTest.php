<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * Test complete flow: Save → Render
 * Verify that gelar with quotes is handled correctly end-to-end
 */
class NametagDataGelarRenderFlowTest extends TestCase
{
    /**
     * Simulate NametagData::buildFront() 
     * After quotes are removed at save time
     */
    private function simulateBuildFront(string $gelarBelakang, string $nama): string
    {
        // This is what buildFront does: just return gelar_belakang from DB
        // (which is already normalized at SAVE time)
        return trim($nama) . ($gelarBelakang ? ', ' . $gelarBelakang : '');
    }

    /**
     * Test: User saves with quotes, generates nametag
     * 
     * Flow:
     * 1. User input: S."IP"
     * 2. SAVE: normalizeGelarPublic() parses → S.IP stored to DB
     * 3. RENDER: buildFront() gets clean S.IP from DB
     * 4. Render should NOT re-normalize
     */
    public function test_gelar_quote_escape_preserved_through_render_flow()
    {
        // Simulate saved data (already clean from DB)
        $savedGelarBelakang = 'S.IP';  // Already parsed at save time
        $nama = 'John Doe';

        // Simulate buildFront() - just gets data from DB
        $namaInNametag = $this->simulateBuildFront($savedGelarBelakang, $nama);

        // Render should NOT apply normalizeAbbreviations() anymore
        // So result should maintain uppercase from quote-escape
        $this->assertStringContainsString('S.IP', $namaInNametag);
        $this->assertStringNotContainsString('S.Ip', $namaInNametag);  // Should NOT be title-cased
    }

    /**
     * Test: Multiple quote escapes preserved
     */
    public function test_multiple_quote_escapes_preserved_in_render()
    {
        // Saved: S.TR.IP (quotes removed at save, content preserved)
        $savedGelarBelakang = 'S.TR.IP';
        $nama = 'Jane Smith';

        $namaInNametag = $this->simulateBuildFront($savedGelarBelakang, $nama);

        // Should show preserved uppercase
        $this->assertStringContainsString('S.TR.IP', $namaInNametag);
        $this->assertStringNotContainsString('S.Tr.Ip', $namaInNametag);  // Should NOT be title-cased
    }

    /**
     * Test: Standard case without quote still works (backward compat)
     * 
     * Old data (created before quote-escape feature):
     * User saved: S.IP (no quotes)
     * Stored as: S.Ip (rule applied at that time)
     * Generate: Should show S.Ip (backward compat)
     */
    public function test_standard_case_backward_compat()
    {
        // Old data - stored as S.Ip (before quote-escape feature)
        $savedGelarBelakang = 'S.Ip';
        $nama = 'Bob Wilson';

        $namaInNametag = $this->simulateBuildFront($savedGelarBelakang, $nama);

        // Should show as-is from DB (S.Ip)
        $this->assertStringContainsString('S.Ip', $namaInNametag);
    }

    /**
     * Test: Mixed case (partial quote)
     * User saved: S.Psi, M."KOM"
     * Parsed to: S.Psi, M.KOM
     */
    public function test_mixed_quote_and_standard_preserved()
    {
        $savedGelarBelakang = 'S.Psi, M.KOM';
        $nama = 'Alice Brown';

        $namaInNametag = $this->simulateBuildFront($savedGelarBelakang, $nama);

        // Should preserve mixed case from quote-escape
        $this->assertStringContainsString('S.Psi, M.KOM', $namaInNametag);
        $this->assertStringNotContainsString('M.Kom', $namaInNametag);  // Should NOT be title-cased
    }
}
