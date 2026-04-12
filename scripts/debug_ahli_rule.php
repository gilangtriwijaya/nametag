<?php
/**
 * Test script untuk debug AHLI rule tidak berfungsi
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

// Test data
$text = "PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA";
$font = public_path('fonts/OpenSans-Regular.ttf');
// ACTUAL width from config: 48mm @ 300 DPI = 567px
$width = (int)round((48 / 25.4) * 300);  // = 567px
$size = 15;   // px

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ TESTING AHLI ATOMIC RULE - DEBUG                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Input Text: \"$text\"\n";
echo "Font Size: {$size}px, Width: {$width}px\n\n";

// NametagTextLayout is a trait, need to use NametagRenderService which uses it
$renderService = app(\App\Services\NametagRenderService::class);

// Use reflection to call private method
$ref = new ReflectionClass($renderService);

// Test wrapLines
$wrapMethod = $ref->getMethod('wrapLines');
$wrapMethod->setAccessible(true);
$lines = $wrapMethod->invoke($renderService, $text, $width, $font, $size);

echo "=== STEP 1: WRAPPED LINES (BEFORE POST-PROCESS) ===\n";
foreach ($lines as $idx => $line) {
    echo "Line " . ($idx + 1) . ": \"$line\"\n";
}
echo "Total lines: " . count($lines) . "\n\n";

// Check if "Ahli" is at end of any line
echo "=== STEP 2: CHECK IF 'AHLI' SEPARATED ===\n";
$ahliSeparated = false;
for ($i = 0; $i < count($lines); $i++) {
    $match = preg_match('/\bAhli\s*$/iu', $lines[$i]);
    echo "Line " . ($i + 1) . " ends with 'Ahli'? " . ($match ? "YES" : "NO") . "\n";
    if ($match && $i < count($lines) - 1) {
        $ahliSeparated = true;
        echo "   ⚠️  'Ahli' is separated from next line!\n";
    }
}
echo "\n";

// Now test the ensure function
$ensureMethod = $ref->getMethod('ensureAhliAtomicAfterWrap');
$ensureMethod->setAccessible(true);

echo "=== STEP 3: CALLING ensureAhliAtomicAfterWrap() ===\n";
echo "Params:\n";
echo "  lines: " . json_encode($lines) . "\n";
echo "  fullText: \"$text\"\n";
echo "  width: $width\n";
echo "  font: $font\n";
echo "  size: $size\n\n";

$linesAfter = $ensureMethod->invoke($renderService, $lines, $text, $width, $font, $size);

echo "=== RESULT AFTER POST-PROCESS ===\n";
foreach ($linesAfter as $idx => $line) {
    echo "Line " . ($idx + 1) . ": \"$line\"\n";
}
echo "Total lines: " . count($linesAfter) . "\n\n";

// Comparison
if (count($lines) !== count($linesAfter)) {
    echo "✓✓✓ LINES CHANGED! Rule applied successfully\n";
} else {
    echo "✗✗✗ LINES UNCHANGED! Rule did NOT apply\n";
}

// Check if "Ahli" still separated
echo "\n=== FINAL CHECK ===\n";
$ahliStillSeparated = false;
for ($i = 0; $i < count($linesAfter); $i++) {
    if (preg_match('/\bAhli\s*$/iu', $linesAfter[$i]) && $i < count($linesAfter) - 1) {
        $ahliStillSeparated = true;
        echo "⚠️  'Ahli' STILL separated at line " . ($i + 1) . "\n";
    }
}

if (!$ahliStillSeparated && $ahliSeparated) {
    echo "✓✓✓ SUCCESS: 'Ahli' is now ATOMIC\n";
} elseif (!$ahliSeparated) {
    echo "✓ ALREADY ATOMIC: Did not need fixing\n";
} else {
    echo "✗✗✗ FAILED: 'Ahli' is STILL separated\n";
}
