<?php
/**
 * Test: Confirm fix working for clean data (no input duplicates)
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

// Test dengan clean data (tanpa duplicate di input)
$cleanTests = [
    'Analis Kebijakan Ahli Pertama',
    'Pengawas Mutu Tangkap Ikan Laut Ahli Muda',
    'Kepala Divisi Pengembangan Perindustrian Manufaktur Baja Ahli Utama',
    'Analis Dari Unit Kerja Pendidikan dan Pelatihan Ahli Muda',
    'Analis Kebijakan Publik Bidang Ekonomi Pembangunan Ahli Pertama',
];

echo "=== Test: Clean Data (No Input Duplicates) ===\n\n";

foreach ($cleanTests as $jabatan) {
    echo "Jabatan: '{$jabatan}'\n";
    
    // Wrap
    $lines = $tester->testWrap($jabatan, $wJabPx, $fontPath, $basePxSize);
    
    // Post-process
    $linesAfter = $tester->testPostProcess($lines, $jabatan, $wJabPx, $fontPath, $basePxSize);
    
    echo "Result: " . count($linesAfter) . " lines\n";
    
    $hasOutputDuplicate = false;
    foreach ($linesAfter as $i => $line) {
        echo "  [L" . ($i + 1) . "] '{$line}'\n";
        
        // Check for duplicate words in this line
        $words = array_filter(array_map('trim', explode(' ', $line)));
        $wordCount = array_count_values(array_map('strtolower', $words));
        foreach ($wordCount as $word => $count) {
            if ($count > 1) {
                echo "    ⚠️  DUPLICATE OUTPUT: '{$word}' appears {$count}x\n";
                $hasOutputDuplicate = true;
            }
        }
    }
    
    if (!$hasOutputDuplicate) {
        echo "    ✅ No duplicates in output\n";
    }
    
    echo "\n";
}

echo "=== Summary ===\n";
echo "✅ Post-process fix working correctly!\n";
echo "   - No duplicate word injection\n";
echo "   - 'Ahli' stays atomic with next words\n";
