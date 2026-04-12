<?php
/**
 * Test script: Verify gelar normalization happens at SAVE time
 * 
 * This simulates the full flow:
 * 1. User inputs gelar with quotes (e.g., S."IP")
 * 2. EmployeeService.normalizeNameDegree() should parse and remove quotes
 * 3. DB should store clean gelar (S.IP)
 * 4. Display views will show clean gelar without parsing again
 */

// Standalone test without Laravel bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

class TestGelarSaveFlow
{
    /**
     * Replicate the normalizeGelar logic from NametagData
     */
    public static function normalizeGelar(string $s): string
    {
        // Step 1: Extract and preserve content inside double quotes
        $preservedMap = [];
        $placeholder = '__PRESERVED_%d__';
        
        $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $placeholder) {
            $idx = count($preservedMap);
            $key = sprintf($placeholder, $idx);
            $preservedMap[$key] = $matches[1];  // Store exact content inside quotes
            return $key;  // Replace with placeholder temporarily
        }, $s);
        
        // Step 2: Apply normalization on remaining parts (split by comma)
        $parts = array_map('trim', explode(',', $s));
        $outParts = [];
        
        foreach ($parts as $part) {
            if ($part === '') continue;
            
            // split into segments separated by dots, but keep dots when rebuilding
            $segs = preg_split('/(\.)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            for ($i = 0; $i < count($segs); $i++) {
                $seg = $segs[$i];
                
                // Skip dots
                if ($seg === '.') continue;
                if ($seg === '') continue;
                
                // If this segment is a placeholder (preserved content), keep as-is
                if (preg_match('/__PRESERVED_\d+__/', $seg)) {
                    continue;  // Will be restored later
                }
                
                // Otherwise, apply standard normalization: ucfirst(lowercase)
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
     * Simulate what EmployeeService.normalizeNameDegree() does
     * This is called BEFORE saving to DB
     */
    public static function simulateEmployeeServiceSave(array $data): array
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
                $v = self::normalizeGelar($v);  // <-- THE FIX: Parse quotes at save time
            }

            $data[$k] = $v;
        }
        
        return $data;
    }

    public static function run()
    {
        $tests = [
            // Format: ['input' => 'user input', 'dbExpected' => 'what should be in DB', 'label' => 'test name']
            [
                'input'      => 'S."IP"',
                'dbExpected' => 'S.IP',
                'label'      => 'Single quote escape: S."IP" → S.IP',
            ],
            [
                'input'      => 'S."TR"."IP"',
                'dbExpected' => 'S.TR.IP',
                'label'      => 'Multiple quote escapes: S."TR"."IP" → S.TR.IP (preserve all)',
            ],
            [
                'input'      => 'S.Psi, M."KOM"',
                'dbExpected' => 'S.Psi, M.KOM',
                'label'      => 'Mixed: S.Psi, M."KOM" → S.Psi, M.KOM',
            ],
            [
                'input'      => 'S.IP',
                'dbExpected' => 'S.Ip',
                'label'      => 'No quotes (standard rule): S.IP → S.Ip',
            ],
            [
                'input'      => 'S.I.KOM., M.KESOS',
                'dbExpected' => 'S.I.Kom., M.Kesos',
                'label'      => 'Comma-separated without quotes: S.I.KOM., M.KESOS → S.I.Kom., M.Kesos',
            ],
            [
                'input'      => 'S."I.KOM."',
                'dbExpected' => 'S.I.KOM.',
                'label'      => 'Quote escape entire segment: S."I.KOM." → S.I.KOM.',
            ],
            [
                'input'      => '  S."IP"  ',
                'dbExpected' => 'S.IP',
                'label'      => 'Trim whitespace: "  S."IP"  " → S.IP',
            ],
            [
                'input'      => 'S.Psi',
                'dbExpected' => 'S.Psi',
                'label'      => 'Standard multi-letter: S.Psi → S.Psi (unchanged)',
            ],
        ];

        $passed = 0;
        $failed = 0;

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST: Gelar Save Flow (Parse at SAVE time)\n";
        echo str_repeat("=", 80) . "\n\n";

        foreach ($tests as $test) {
            $input = $test['input'];
            $expected = $test['dbExpected'];
            $label = $test['label'];

            // Simulate: User inputs gelar with quotes
            $inputData = ['gelar_belakang' => $input];
            
            // Simulate: EmployeeService.normalizeNameDegree() is called before save
            $savedData = self::simulateEmployeeServiceSave($inputData);
            
            // Get what would be saved to DB
            $dbValue = $savedData['gelar_belakang'];
            
            // Check if it matches expected
            $isPass = ($dbValue === $expected);
            
            if ($isPass) {
                echo "✅ PASS: $label\n";
                echo "   Input:    '$input'\n";
                echo "   DB saved: '$dbValue'\n";
                $passed++;
            } else {
                echo "❌ FAIL: $label\n";
                echo "   Input:      '$input'\n";
                echo "   Expected:   '$expected'\n";
                echo "   DB actual:  '$dbValue'\n";
                $failed++;
            }
            echo "\n";
        }

        echo str_repeat("=", 80) . "\n";
        echo "SUMMARY: $passed passed, $failed failed\n";
        echo str_repeat("=", 80) . "\n\n";

        return $failed === 0;
    }
}

// Run tests
$success = TestGelarSaveFlow::run();
exit($success ? 0 : 1);
