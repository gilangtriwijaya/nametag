<?php
/**
 * Proper debug script untuk AHLI rule - menggunakan fitWrappedLinesPx
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

// Test data
$text = "PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA";
$font = public_path('fonts/OpenSans-Regular.ttf');
$width = (int)round((48 / 25.4) * 300); // 48mm @ 300 DPI = 567px

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ TESTING AHLI ATOMIC RULE - WITH PROPER FONT SIZE               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Input Text: \"$text\"\n";
echo "Template Width: {$width}px\n";
echo "Max Lines: 2\n\n";

// Get NametagRenderService
$renderService = app(\App\Services\NametagRenderService::class);

// Use reflection to call private methods
$ref = new ReflectionClass($renderService);

// Step 1: Calculate base font size (from config, matching NametagRenderService logic)
$ppm = 300 / 25.4; // pixels per mm @ 300 DPI
$baseSize = 5.5 * $ppm * 0.92; // base font size from config for jabatan field
echo "=== STEP 1: CALCULATE BASE FONT SIZE ===\n";
echo "Base size (5.5mm @ 300 DPI * 0.92): {$baseSize}px\n\n";

// Step 2: Find optimal font size using fitWrappedLinesPx
$fitMethod = $ref->getMethod('fitWrappedLinesPx');
$fitMethod->setAccessible(true);
$fittedSize = $fitMethod->invoke($renderService, $text, $font, $baseSize, $width, 2);
echo "=== STEP 2: FIND OPTIMAL FONT SIZE ===\n";
echo "Fitted size for 2-line wrap: {$fittedSize}px\n\n";

// Step 3: Wrap text with fitted font size
$wrapMethod = $ref->getMethod('wrapLines');
$wrapMethod->setAccessible(true);
$lines = $wrapMethod->invoke($renderService, $text, $width, $font, $fittedSize);

echo "=== STEP 3: WRAPPED LINES (BEFORE POST-PROCESS) ===\n";
foreach ($lines as $idx => $line) {
    echo "Line " . ($idx + 1) . ": \"$line\"\n";
}
echo "Total lines: " . count($lines) . "\n\n";

// Step 4: Check if "Ahli" is at end of any line
echo "=== STEP 4: CHECK IF 'AHLI' SEPARATED ===\n";
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

// Step 5: Apply ensureAhliAtomicAfterWrap
echo "=== STEP 5: CALLING ensureAhliAtomicAfterWrap() ===\n";
if ($ahliSeparated || true) { // Always call to see what it does
    $ahliMethod = $ref->getMethod('ensureAhliAtomicAfterWrap');
    $ahliMethod->setAccessible(true);
    
    echo "Params:\n";
    echo "  lines: " . json_encode($lines) . "\n";
    echo "  fullText: \"$text\"\n";
    echo "  width: $width\n";
    echo "  font: $font\n";
    echo "  size: $fittedSize\n\n";
    
    $result = $ahliMethod->invoke($renderService, $lines, $text, $width, $font, $fittedSize);
    
    echo "=== RESULT AFTER POST-PROCESS ===\n";
    foreach ($result as $idx => $line) {
        echo "Line " . ($idx + 1) . ": \"$line\"\n";
    }
    echo "Total lines: " . count($result) . "\n\n";
    
    // Compare
    if ($lines === $result) {
        echo "✗✗✗ LINES UNCHANGED! Rule did NOT apply\n";
        if (!$ahliSeparated) {
            echo "✓ ALREADY ATOMIC: Did not need fixing\n";
        } else {
            echo "✗✗✗ BUG: 'Ahli' was separated but rule didn't fix it!\n";
        }
    } else {
        echo "✓✓✓ LINES CHANGED! Rule applied successfully\n";
        echo "\nBefore:\n";
        foreach ($lines as $idx => $line) {
            echo "  Line " . ($idx + 1) . ": \"$line\"\n";
        }
        echo "After:\n";
        foreach ($result as $idx => $line) {
            echo "  Line " . ($idx + 1) . ": \"$line\"\n";
        }
    }
}
