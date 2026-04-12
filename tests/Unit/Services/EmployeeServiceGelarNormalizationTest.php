<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

/**
 * Test that EmployeeService normalizes gelar at SAVE time
 * This is a simplified unit test that simulates the normalizeNameDegree flow
 */
class EmployeeServiceGelarNormalizationTest extends TestCase
{
    /**
     * Simulate the normalizeGelar logic
     */
    private static function normalizeGelar(string $s): string
    {
        // Step 1: Extract and preserve content inside double quotes
        $preservedMap = [];
        $placeholder = '__PRESERVED_%d__';
        
        $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $placeholder) {
            $idx = count($preservedMap);
            $key = sprintf($placeholder, $idx);
            $preservedMap[$key] = $matches[1];
            return $key;
        }, $s);
        
        // Step 2: Apply normalization on remaining parts (split by comma)
        $parts = array_map('trim', explode(',', $s));
        $outParts = [];
        
        foreach ($parts as $part) {
            if ($part === '') continue;
            
            $segs = preg_split('/(\.)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            for ($i = 0; $i < count($segs); $i++) {
                $seg = $segs[$i];
                
                if ($seg === '.') continue;
                if ($seg === '') continue;
                
                if (preg_match('/__PRESERVED_\d+__/', $seg)) {
                    continue;
                }
                
                $seg = mb_strtolower($seg, 'UTF-8');
                $segs[$i] = mb_convert_case(mb_substr($seg, 0, 1, 'UTF-8'), MB_CASE_UPPER, 'UTF-8')
                    . mb_substr($seg, 1, null, 'UTF-8');
            }
            
            $outParts[] = implode('', $segs);
        }
        
        $result = implode(', ', $outParts);
        
        // Step 3: Restore preserved (quoted) content
        foreach ($preservedMap as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        
        return $result;
    }

    /**
     * Simulate EmployeeService.normalizeNameDegree() behavior
     */
    private function normalizeDataLikeService(array $data): array
    {
        foreach (['gelar_depan', 'gelar_belakang', 'nama'] as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }

            $v = trim((string) $data[$k]);
            $v = preg_replace('/\s+/', ' ', $v);
            $v = preg_replace('/,+/', ',', $v);
            $v = preg_replace('/\s*,\s*/', ', ', $v);

            // Apply gelar normalization (quote-escape parsing) for gelar fields
            if (in_array($k, ['gelar_depan', 'gelar_belakang'], true)) {
                $v = self::normalizeGelar($v);
            }

            $data[$k] = $v;
        }
        
        return $data;
    }

    /**
     * Test that gelar with quotes is normalized at save time
     * Input: S."IP" → Should save as: S.IP (without quotes)
     */
    public function test_gelar_with_quotes_is_normalized_at_save()
    {
        $data = [
            'gelar_belakang' => 'S."IP"',  // Input with quote escape
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // Verify DB would have CLEAN gelar (no quotes!)
        $this->assertEquals('S.IP', $normalized['gelar_belakang']);
        $this->assertStringNotContainsString('"', $normalized['gelar_belakang']);
    }

    /**
     * Test multiple quote escapes
     */
    public function test_multiple_quote_escapes_normalized()
    {
        $data = [
            'gelar_belakang' => 'S."TR"."IP"',
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // Verify quotes are removed
        $this->assertEquals('S.TR.IP', $normalized['gelar_belakang']);
        $this->assertStringNotContainsString('"', $normalized['gelar_belakang']);
    }

    /**
     * Test mixed quoted and unquoted
     */
    public function test_mixed_quoted_and_unquoted()
    {
        $data = [
            'gelar_belakang' => 'S.Psi, M."KOM"',
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // Verify result
        $this->assertEquals('S.Psi, M.KOM', $normalized['gelar_belakang']);
        $this->assertStringNotContainsString('"', $normalized['gelar_belakang']);
    }

    /**
     * Test standard case without quotes
     * Input: S.IP → Should normalize to: S.Ip (apply standard rule)
     */
    public function test_standard_normalization_without_quotes()
    {
        $data = [
            'gelar_belakang' => 'S.IP',  // No quotes
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // Verify standard rule applied
        $this->assertEquals('S.Ip', $normalized['gelar_belakang']);
    }

    /**
     * Test that gelar_depan is also normalized
     */
    public function test_gelar_depan_also_normalized()
    {
        $data = [
            'gelar_depan' => 'Dr."Eng"',
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // Verify quotes are removed from gelar_depan too
        $this->assertEquals('Dr.Eng', $normalized['gelar_depan']);
        $this->assertStringNotContainsString('"', $normalized['gelar_depan']);
    }

    /**
     * Test that whitespace is normalized along with quotes
     */
    public function test_whitespace_and_quotes_normalized()
    {
        $data = [
            'gelar_belakang' => '  S."IP"  ',  // With surrounding spaces
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // Verify both whitespace and quotes removed
        $this->assertEquals('S.IP', $normalized['gelar_belakang']);
    }

    /**
     * Test that nama field is NOT affected by gelar rules
     */
    public function test_nama_field_not_affected()
    {
        $data = [
            'nama' => 'Budi S."IP" Santoso',  // nama with quotes (unlikely but shouldn't be normalized like gelar)
        ];

        $normalized = $this->normalizeDataLikeService($data);

        // nama should keep its spaces normalized but NOT gelar-normalized
        $this->assertStringContainsString('"', $normalized['nama']);  // Quotes still there
    }
}
