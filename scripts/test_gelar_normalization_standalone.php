<?php
/**
 * Standalone test untuk normalizeGelar quote escape feature
 * Tidak memerlukan Laravel bootstrap
 */

// Copy dari app/Support/NametagData.php - private function normalizeGelar
function normalizeGelar(string $s): string
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
        $segs = preg_split('/(\.)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
        
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

// Test cases
$testCases = [
    // Standard cases (no quote)
    ['input' => 'S.T', 'expected' => 'S.T', 'label' => 'Single letter: S.T'],
    ['input' => 'S.Psi', 'expected' => 'S.Psi', 'label' => 'Multi-letter: S.Psi'],
    ['input' => 'S.I.P', 'expected' => 'S.I.P', 'label' => 'Multiple single: S.I.P'],
    ['input' => 'M.KOM', 'expected' => 'M.Kom', 'label' => 'Uppercase normalize: M.KOM → M.Kom'],
    ['input' => 'S.I.KOM., M.KESOS', 'expected' => 'S.I.Kom., M.Kesos', 'label' => 'Comma-separated: normalize both'],
    
    // With quote escape
    ['input' => 'S."IP"', 'expected' => 'S.IP', 'label' => 'Quote escape: S."IP" → S.IP'],
    ['input' => 'M."KOM"', 'expected' => 'M.KOM', 'label' => 'Quote escape: M."KOM" → M.KOM'],
    ['input' => 'S."Tr"."IP"', 'expected' => 'S.Tr.IP', 'label' => 'Multiple quotes: S."Tr"."IP" → S.Tr.IP'],
    ['input' => 'S.I."P"', 'expected' => 'S.I.P', 'label' => 'Partial quote: S.I."P" → S.I.P'],
    ['input' => 'D."R"', 'expected' => 'D.R', 'label' => 'Single char quote: D."R" → D.R'],
    
    // Mixed
    ['input' => 'S.Psi, M."KOM"', 'expected' => 'S.Psi, M.KOM', 'label' => 'Mixed: normal + quote'],
    ['input' => 'D.R.S."Honoris Causa"', 'expected' => 'D.R.S.Honoris Causa', 'label' => 'Multi-word quote: preserve phrase'],
];

echo "═══════════════════════════════════════════════════════════════\n";
echo "  TESTING: normalizeGelar Quote Escape Feature\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    $input = $test['input'];
    $expected = $test['expected'];
    $label = $test['label'];
    
    $result = normalizeGelar($input);
    
    $status = $result === $expected ? '✅ PASS' : '❌ FAIL';
    $passed += ($result === $expected ? 1 : 0);
    $failed += ($result !== $expected ? 1 : 0);
    
    echo "{$status} | {$label}\n";
    
    if ($result !== $expected) {
        echo "     Input:    '{$input}'\n";
        echo "     Expected: '{$expected}'\n";
        echo "     Got:      '{$result}'\n\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  Results: {$passed} passed, {$failed} failed\n";
echo "═══════════════════════════════════════════════════════════════\n";

exit($failed > 0 ? 1 : 0);
