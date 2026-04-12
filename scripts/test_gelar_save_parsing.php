<?php
/**
 * Test script to verify gelar parsing during save (normalizeGelarPublic)
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load NametagData class
require_once __DIR__ . '/../app/Support/NametagData.php';

use App\Support\NametagData;

// Test cases: input with quotes that should be parsed during save
$testCases = [
    // Standard cases without quotes (backward compat)
    ['input' => 'S.IP', 'expected' => 'S.Ip', 'desc' => 'Standard: S.IP'],
    ['input' => 'S.T', 'expected' => 'S.T', 'desc' => 'Standard: S.T'],
    ['input' => 'S.Psi', 'expected' => 'S.Psi', 'desc' => 'Standard: S.Psi'],
    
    // Quote escape cases (NEW FEATURE)
    ['input' => 'S."IP"', 'expected' => 'S.IP', 'desc' => 'Quote escape: S."IP" → S.IP'],
    ['input' => 'S."Tr.IP"', 'expected' => 'S.Tr.IP', 'desc' => 'Quote with dot: S."Tr.IP" → S.Tr.IP'],
    ['input' => 'S."TR"."IP"', 'expected' => 'S.TR.IP', 'desc' => 'Multiple quotes: S."TR"."IP" → S.TR.IP'],
    ['input' => 'M."KOM"', 'expected' => 'M.KOM', 'desc' => 'Quote escape: M."KOM" → M.KOM'],
    
    // Mixed normal and quoted
    ['input' => 'S.Psi, M."KOM"', 'expected' => 'S.Psi, M.KOM', 'desc' => 'Mixed: S.Psi, M."KOM" → S.Psi, M.KOM'],
    ['input' => 'S."Tr", M.Kom', 'expected' => 'S.Tr, M.Kom', 'desc' => 'Mixed: S."Tr", M.Kom → S.Tr, M.Kom'],
    
    // Edge cases
    ['input' => '', 'expected' => '', 'desc' => 'Empty string'],
    ['input' => 'S', 'expected' => 'S', 'desc' => 'Single letter'],
];

echo "========================================\n";
echo "TEST: normalizeGelarPublic() - Quote Parsing\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    $input = $test['input'];
    $expected = $test['expected'];
    $desc = $test['desc'];
    
    try {
        $result = NametagData::normalizeGelarPublic($input);
        $pass = ($result === $expected);
        
        if ($pass) {
            echo "✅ PASS: $desc\n";
            echo "   Input:    \"$input\"\n";
            echo "   Output:   \"$result\"\n";
            echo "   Expected: \"$expected\"\n";
            $passed++;
        } else {
            echo "❌ FAIL: $desc\n";
            echo "   Input:    \"$input\"\n";
            echo "   Output:   \"$result\"\n";
            echo "   Expected: \"$expected\"\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "⚠️  ERROR: $desc\n";
        echo "   Input: \"$input\"\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $failed++;
    }
    echo "\n";
}

echo "========================================\n";
echo "SUMMARY: $passed passed, $failed failed\n";
echo "========================================\n";

exit($failed === 0 ? 0 : 1);
