<?php
/**
 * Quick validation script untuk normalizeGelar quote escape feature
 * Run: php scripts/test_gelar_normalization.php
 */

require_once __DIR__ . '/../bootstrap/app.php';

use App\Support\NametagData;
use Illuminate\Contracts\Container\Container;

// Create application instance
$app = require_once __DIR__ . '/../bootstrap/app.php';

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
    
    // Call via reflection (since method is private)
    $reflection = new ReflectionClass(NametagData::class);
    $method = $reflection->getMethod('normalizeGelar');
    $method->setAccessible(true);
    $result = $method->invoke(null, $input);
    
    $status = $result === $expected ? '✅ PASS' : '❌ FAIL';
    $passed += ($result === $expected ? 1 : 0);
    $failed += ($result !== $expected ? 1 : 0);
    
    echo "{$status} | {$label}\n";
    
    if ($result !== $expected) {
        echo "     Input:    {$input}\n";
        echo "     Expected: {$expected}\n";
        echo "     Got:      {$result}\n\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "═══════════════════════════════════════════════════════════════\n";

exit($failed > 0 ? 1 : 0);
