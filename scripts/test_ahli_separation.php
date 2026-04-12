<?php
/**
 * Test untuk kasus di mana "Ahli" benar-benar terpisah
 * Jabatan panjang yang force "Ahli" ke line berikutnya
 */

use App\Services\Nametag\NametagTextLayout;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

$fontPath = __DIR__ . '/../public/fonts/OpenSans-Bold.ttf';
$ppm = 10.48;
$wJabPx = (int)round(48 * $ppm);
$basePxSize = 2 * $ppm * 0.92;

$tester = new class {
    use NametagTextLayout;
    
    public function testWrap($text, $w, $font, $size) {
        return $this->wrapLines($text, $w, $font, $size);
    }
    
    public function testPostProcess($lines, $fullText, $w, $font, $size) {
        return $this->ensureAhliAtomicAfterWrap($lines, $fullText, $w, $font, $size);
    }
};

// Jabatan yang akan force "Ahli" ke baris baru dalam wrapping
$testJabatan = 'Kepala Divisi Pengembangan Perindustrian Manufaktur Baja Ahli Utama';

echo "=== Test Case: Ahli Separation Detection & Fix ===\n\n";
echo "Jabatan: '{$testJabatan}'\n";
echo "Length: " . strlen($testJabatan) . " chars\n";
echo "Width available: {$wJabPx}px\n\n";

$linesBefore = $tester->testWrap($testJabatan, $wJabPx, $fontPath, $basePxSize);
echo "BEFORE Post-Process:\n";
echo "Lines: " . count($linesBefore) . "\n";
foreach ($linesBefore as $i => $line) {
    $isAhliEnd = preg_match('/\bAhli\s*$/iu', $line);
    $marker = $isAhliEnd ? ' ← 🚨 AHLI at END-OF-LINE' : '';
    echo sprintf("  [L%d] '%s'%s\n", $i + 1, $line, $marker);
}

// Check separation
$ahliSeparation = -1;
for ($i = 0; $i < count($linesBefore) - 1; $i++) {
    if (preg_match('/\bAhli\s*$/iu', $linesBefore[$i])) {
        $ahliSeparation = $i;
        break;
    }
}

if ($ahliSeparation >= 0) {
    $nextLine = trim($linesBefore[$ahliSeparation + 1]);
    echo "\n⚠️  SEPARATION DETECTED at Line " . ($ahliSeparation + 1) . ":\n";
    echo "   'Ahli' separated from '" . $nextLine . "'\n";
}

echo "\n" . str_repeat("→ ", 40) . "\n";
echo "Applying ensureAhliAtomicAfterWrap()...\n";
echo str_repeat("← ", 40) . "\n\n";

$linesAfter = $tester->testPostProcess($linesBefore, $testJabatan, $wJabPx, $fontPath, $basePxSize);

echo "AFTER Post-Process:\n";
echo "Lines: " . count($linesAfter) . "\n";
foreach ($linesAfter as $i => $line) {
    $status = preg_match('/\bAhli\s*$/iu', $line) ? ' ✗' : ' ✓';
    echo sprintf("  [L%d] '%s'%s\n", $i + 1, $line, $status);
}

// Verify fix
$stillSeparated = false;
for ($i = 0; $i < count($linesAfter) - 1; $i++) {
    if (preg_match('/\bAhli\s*$/iu', $linesAfter[$i])) {
        $stillSeparated = true;
        break;
    }
}

echo "\n";
if ($ahliSeparation >= 0) {
    if ($stillSeparated) {
        echo "❌ FIX FAILED: 'Ahli' still separated\n";
    } else {
        echo "✅ FIX SUCCESS: 'Ahli' now together with next words!\n";
        echo "   Total lines: " . count($linesAfter) . " (may be more after fix)\n";
    }
} else {
    echo "ℹ️  No separation to fix - 'Ahli' was already atomic\n";
}
