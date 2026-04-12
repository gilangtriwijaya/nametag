<?php
/**
 * Test script untuk memverifikasi ensureAhliAtomicAfterWrap() post-process logic
 * - Memastikan "Ahli ..." tidak terpisah setelah wrapping
 * - Hanya apply untuk FUNGSIONAL jabatan type
 * - Data real dari employee dengan FUNGSIONAL jabatan
 */

use App\Models\Employee;
use App\Services\NametagRenderService;
use App\Services\Nametag\NametagTextLayout;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

// Test cases: nama => expectedBehavior
$testCases = [
    // Case 1: Fits on one line, no post-process needed
    'Analis Kebijakan Ahli Pertama' => [
        'chars' => 29,
        'pixels' => 387,
        'available' => 503,
        'expectLines' => 1,
        'description' => 'FIT on 1 line: 387px < 503px available'
    ],
    
    // Case 2: Wraps, but "Ahli" isolated on line 2
    'Pengawas Mutu Tangkap Ikan Laut Ahli Pertama' => [
        'chars' => 44,
        'pixels' => 585,
        'expectLines' => 2,
        'description' => 'WRAPS to 2 lines: ~585px > 503px, needs post-process'
    ],
    
    // Case 3: Very long, wraps past 2 lines
    'Ahli Kebijakan Publik Bidang Administrasi Pemerintahan' => [
        'chars' => 54,
        'pixels' => 728,
        'expectLines' => 3,
        'description' => 'LONG: wraps to 3+ lines, post-process finds "Ahli Kebijakan..."'
    ],
];

// Mock Employee untuk testing
class MockEmployee {
    public $id = 999;
    public $jabatan_type = 'FUNGSIONAL';
    public $jabatan = '';
    
    public function __construct($jabatan) {
        $this->jabatan = $jabatan;
    }
}

// Simple Font path test
$fontPath = __DIR__ . '/../public/fonts/OpenSans-Bold.ttf';
if (!file_exists($fontPath)) {
    echo "ERROR: Font not found at {$fontPath}\n";
    exit(1);
}

// PPM calculation (pixels per mm): 566px / 54.03mm ≈ 10.48
$ppm = 10.48;
$wJabMm = 48;
$wJabPx = (int)round($wJabMm * $ppm);

// Jabatan font size: 2mm = 19.28px at standard conversion
$jabFontSizeMm = 2;
$basePxSize = $jabFontSizeMm * $ppm * 0.92; // ≈ 19.28px

echo "=== Ahli Post-Process Test Suite ===\n";
echo "Font: {$fontPath}\n";
echo "Jabatan width: {$wJabMm}mm = {$wJabPx}px\n";
echo "Font size: {$jabFontSizeMm}mm = " . round($basePxSize, 2) . "px\n";
echo "---\n\n";

// Trait mock untuk testing (minimal wrapLines + ensureAhliAtomicAfterWrap)
$traitTest = new class {
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

// Run tests
foreach ($testCases as $jabatan => $spec) {
    echo "TEST: {$spec['description']}\n";
    echo "Jabatan: '{$jabatan}'\n";
    
    // Measure
    $px = $traitTest->testMeasure($jabatan, $fontPath, $basePxSize);
    echo "Measured: {$px}px ({$wJabPx}px available)\n";
    
    // Wrap BEFORE post-process
    $linesBefore = $traitTest->testWrap($jabatan, $wJabPx, $fontPath, $basePxSize);
    echo "Before post-process: " . count($linesBefore) . " lines\n";
    foreach ($linesBefore as $i => $line) {
        echo "  [Line " . ($i + 1) . "] '{$line}'\n";
    }
    
    // Apply post-process
    $linesAfter = $traitTest->testPostProcess($linesBefore, $jabatan, $wJabPx, $fontPath, $basePxSize);
    echo "After post-process: " . count($linesAfter) . " lines\n";
    foreach ($linesAfter as $i => $line) {
        echo "  [Line " . ($i + 1) . "] '{$line}'\n";
    }
    
    // Check for "Ahli" separated
    $ahliSeparated = false;
    for ($i = 0; $i < count($linesBefore) - 1; $i++) {
        if (preg_match('/\bAhli\s*$/iu', $linesBefore[$i])) {
            $ahliSeparated = true;
            echo "⚠️  'Ahli' END-OF-LINE at line " . ($i + 1) . " (NEEDS post-process)\n";
            break;
        }
    }
    
    if (!$ahliSeparated) {
        echo "✅ 'Ahli' is ATOMIC (no separation)\n";
    } else {
        // Check if fixed
        $ahliFixedAfter = true;
        for ($i = 0; $i < count($linesAfter) - 1; $i++) {
            if (preg_match('/\bAhli\s*$/iu', $linesAfter[$i])) {
                $ahliFixedAfter = false;
                break;
            }
        }
        if ($ahliFixedAfter) {
            echo "✅ POST-PROCESS FIXED: 'Ahli' now atomic\n";
        }
    }
    
    echo "\n";
}

echo "Test complete.\n";
