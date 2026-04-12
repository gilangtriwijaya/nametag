<?php
/**
 * Quick sanity test: ensure post-process wrapping tidak duplicate words
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

// Test cases dari nametag IMG
$testData = [
    [
        'name' => 'Antang Wibowo (ASN)',
        'jabatan' => 'Pengelola Pengadaan Barang/Jasa Ahli Muda Muda',
        'type' => 'ASN'
    ],
    [
        'name' => 'Zulkifli (PPPK)',
        'jabatan' => 'Analis Kebijakan Ahli Pertama',
        'type' => 'PPPK'
    ],
    [
        'name' => 'Ardiansyah (ASN)',
        'jabatan' => 'Pengelola Pengadaan Barang/Jasa Ahli Pertama Pertama',
        'type' => 'ASN'
    ],
];

echo "=== Sanity Check: Real Nametag Data ===\n\n";

foreach ($testData as $emp) {
    echo "Employee: {$emp['name']} ({$emp['type']})\n";
    echo "Jabatan: '{$emp['jabatan']}'\n";
    
    // Wrap
    $lines = $tester->testWrap($emp['jabatan'], $wJabPx, $fontPath, $basePxSize);
    
    // Post-process (only if FUNGSIONAL, else skip)
    // But for testing, we'll force it on all
    $linesAfter = $tester->testPostProcess($lines, $emp['jabatan'], $wJabPx, $fontPath, $basePxSize);
    
    echo "Result: " . count($linesAfter) . " lines\n";
    foreach ($linesAfter as $i => $line) {
        echo "  [L" . ($i + 1) . "] '{$line}'\n";
        
        // Check for duplicate words in this line
        $words = array_filter(array_map('trim', explode(' ', $line)));
        $wordCount = array_count_values(array_map('strtolower', $words));
        foreach ($wordCount as $word => $count) {
            if ($count > 1) {
                echo "    ⚠️  DUPLICATE: '{$word}' appears {$count}x\n";
            }
        }
    }
    
    echo "\n";
}

echo "✅ Sanity check complete - check for any ⚠️  DUPLICATE warnings above\n";
