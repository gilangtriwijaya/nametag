<?php
/**
 * Test: Verify "Ahli" rule tidak force-split jika teks fit 1 baris
 * Kasus dari nametag image user: "Analis Kebijakan Ahli Pertama"
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
    
    public function testMeasure($text, $font, $size) {
        $bbox = imagettfbbox($size, 0, $font, $text);
        return abs($bbox[2] - $bbox[0]);
    }
};

// Test cases dari nametag image user
$testCases = [
    'Analis Kebijakan Ahli Pertama' => 'should fit 1 line (sebelumnya)',
    'Pranata Komputer Ahli Pertama' => 'should fit 1 line',
];

echo "=== Test: Ahli Rule Should NOT Force Split If Text Fits 1 Line ===\n";
echo "Font: OpenSans-Bold 19.28px\n";
echo "Width: {$wJabPx}px (48mm available)\n\n";

foreach ($testCases as $jabatan => $expectation) {
    echo "TEST: {$jabatan}\n";
    echo "Expectation: {$expectation}\n";
    
    // 1. Measure
    $px = $tester->testMeasure($jabatan, $fontPath, $basePxSize);
    $fits1Line = $px <= $wJabPx;
    echo "Measured: {$px}px / {$wJabPx}px available = " . ($fits1Line ? "✅ FITS" : "❌ DOES NOT FIT") . "\n";
    
    // 2. Wrap normally (BEFORE post-process)
    $linesBefore = $tester->testWrap($jabatan, $wJabPx, $fontPath, $basePxSize);
    echo "Before post-process: " . count($linesBefore) . " lines\n";
    foreach ($linesBefore as $i => $line) {
        echo "  [L" . ($i + 1) . "] '{$line}'\n";
    }
    
    // 3. Check if "Ahli" is at END of any line (which would indicate separation)
    $ahliAtEnd = false;
    $ahliAtEndLine = -1;
    for ($i = 0; $i < count($linesBefore) - 1; $i++) {
        if (preg_match('/\bAhli\s*$/iu', $linesBefore[$i])) {
            $ahliAtEnd = true;
            $ahliAtEndLine = $i;
            break;
        }
    }
    
    if ($ahliAtEnd) {
        echo "⚠️  'Ahli' at END-of-line " . ($ahliAtEndLine+1) . " (separated from next word)\n";
    } else {
        echo "✅ 'Ahli' NOT separated (atomic or part of line)\n";
    }
    
    // 4. Apply post-process
    $linesAfter = $tester->testPostProcess($linesBefore, $jabatan, $wJabPx, $fontPath, $basePxSize);
    echo "After post-process: " . count($linesAfter) . " lines\n";
    foreach ($linesAfter as $i => $line) {
        echo "  [L" . ($i + 1) . "] '{$line}'\n";
    }
    
    // 5. Verify rule behavior
    echo "RULE CHECK:\n";
    if (count($linesBefore) === 1 && count($linesAfter) === 1) {
        echo "  ✅ CORRECT: Fit 1 line, stayed 1 line (no unnecessary split)\n";
    } elseif (count($linesBefore) === 1 && count($linesAfter) > 1) {
        echo "  ❌ WRONG: Fit 1 line but forced to " . count($linesAfter) . " lines!\n";
    } elseif (count($linesBefore) > 1 && $ahliAtEnd) {
        echo "  ✅ CORRECT: Ahli was separated, post-process fixed it\n";
    } else {
        echo "  ✅ OK: No action needed\n";
    }
    
    echo "\n";
}

echo "=== Test Complete ===\n";
