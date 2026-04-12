<?php
/**
 * Test untuk menunjukkan efek post-process pada jabatan yang panjang
 * Kasus: "Ahli" terpisah dari tingkat-nya
 */

use App\Services\Nametag\NametagTextLayout;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

$fontPath = __DIR__ . '/../public/fonts/OpenSans-Bold.ttf';
$ppm = 10.48;
$wJabPx = (int)round(48 * $ppm);
$basePxSize = 2 * $ppm * 0.92;

// Trait untuk testing
$tester = new class {
    use NametagTextLayout;
    
    public function testWrap($text, $w, $font, $size) {
        return $this->wrapLines($text, $w, $font, $size);
    }
    
    public function testPostProcess($lines, $fullText, $w, $font, $size) {
        return $this->ensureAhliAtomicAfterWrap($lines, $fullText, $w, $font, $size);
    }
};

// Test case: Jabatan yang panjang, "Ahli" terpisah dalam wrapping
$testJabatan = 'Analis Dari Unit Kerja Pendidikan dan Pelatihan Ahli Muda';

echo "=== Demonstrasi Post-Process: Ahli Separation Fix ===\n\n";
echo "Jabatan: '{$testJabatan}'\n";
echo "Width: {$wJabPx}px (48mm available)\n";
echo "Font size: " . round($basePxSize, 2) . "px\n\n";

// Wrap BEFORE post-process
$linesBefore = $tester->testWrap($testJabatan, $wJabPx, $fontPath, $basePxSize);
echo "BEFORE Post-Process:\n";
echo "Lines: " . count($linesBefore) . "\n";
foreach ($linesBefore as $i => $line) {
    echo sprintf("  [L%d] '%s'\n", $i + 1, $line);
}

// Check if "Ahli" was separated
$ahliSeparated = false;
$separationLine = -1;
for ($i = 0; $i < count($linesBefore) - 1; $i++) {
    if (preg_match('/\bAhli\s*$/iu', $linesBefore[$i])) {
        $ahliSeparated = true;
        $separationLine = $i;
        break;
    }
}

if ($ahliSeparated) {
    echo "\n⚠️  ISSUE FOUND: 'Ahli' ends at line " . ($separationLine + 1) . "\n";
    echo "   Line " . ($separationLine + 1) . ": '" . trim($linesBefore[$separationLine]) . "' (ends with 'Ahli')\n";
    echo "   Line " . ($separationLine + 2) . ": '" . trim($linesBefore[$separationLine + 1]) . "' (next word)\n";
    echo "   → 'Ahli' separated from '" . trim($linesBefore[$separationLine + 1]) . "'\n";
} else {
    echo "\n✅ No separation detected\n";
}

// Apply post-process
echo "\nApplying Post-Process...\n";
$linesAfter = $tester->testPostProcess($linesBefore, $testJabatan, $wJabPx, $fontPath, $basePxSize);

echo "\nAFTER Post-Process:\n";
echo "Lines: " . count($linesAfter) . "\n";
foreach ($linesAfter as $i => $line) {
    echo sprintf("  [L%d] '%s'\n", $i + 1, $line);
}

// Verify fix
$ahliStillSeparated = false;
for ($i = 0; $i < count($linesAfter) - 1; $i++) {
    if (preg_match('/\bAhli\s*$/iu', $linesAfter[$i])) {
        $ahliStillSeparated = true;
        break;
    }
}

if ($ahliStillSeparated) {
    echo "\n❌ POST-PROCESS FAILED: 'Ahli' still separated\n";
} else {
    echo "\n✅ POST-PROCESS SUCCESS: 'Ahli' now atomic!\n";
}

echo "\n=== Summary ===\n";
if ($ahliSeparated) {
    echo "Original: Separated across lines\n";
    echo "Fixed by: ensureAhliAtomicAfterWrap()\n";
} else {
    echo "Original: Already atomic\n";
    echo "No fix needed\n";
}
