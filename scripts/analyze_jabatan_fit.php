<?php
/**
 * Script untuk analyze batas karakter dan fit jabatan dalam 1 baris
 */
// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$testJabatans = [
    'Analis Kebijakan Ahli Pertama',
    'Analis Kebijakan',
    'Ahli Pertama',
    'Analis Kebijakan Ahli',
    'Kepala Bidang Keuangan',
    'Ahli Keuangan Daerah',
    'Ahli Kebijakan Publik Bidang Administrasi Pemerintahan',
    'Analisis Kebijakan Publik Ahli Senior Tingkat I',
];

echo "========== JABATAN FIT ANALYSIS ==========\n\n";

// Get GD info
echo "GD Library Support: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";
echo "ImageTTF Support: " . (function_exists('imagettfbbox') ? 'YES' : 'NO') . "\n\n";

// Font config dari nametag
$cfgFront = config('nametag.templates.front');
$jabatanConfig = null;

if ($cfgFront && isset($cfgFront['texts'])) {
    foreach ($cfgFront['texts'] as $item) {
        if (($item['key'] ?? null) === 'jabatan') {
            $jabatanConfig = $item;
            break;
        }
    }
}

if (!$jabatanConfig) {
    echo "❌ Jabatan config not found\n";
    exit(1);
}

echo "JABATAN CONFIG FROM nametag.php:\n";
echo "  Position X: {$jabatanConfig['x']} mm\n";
echo "  Position Y: {$jabatanConfig['y']} mm\n";
echo "  Width: {$jabatanConfig['w']} mm\n";
echo "  Font Size: {$jabatanConfig['font']['size']} mm\n";
echo "  Font Key: {$jabatanConfig['font']['key']}\n";
echo "  Bold: " . ($jabatanConfig['font']['bold'] ? 'YES' : 'NO') . "\n";
echo "  Line Height: {$jabatanConfig['line_height']}\n";
echo "  Max Wrap: {$jabatanConfig['wrap']} lines\n\n";

// Simulate PPM (pixels per mm)
// Standard nametag size: 54.03mm x 85.63mm
// Common rendered size: ~566px x 898px (for 3x5 inch at 96 DPI)
$nametag_w_mm = 54.03;
$nametag_h_mm = 85.63;
$rendered_w_px = 566;
$ppm = $rendered_w_px / $nametag_w_mm;

echo "PPM (Pixels Per MM): " . round($ppm, 2) . "\n";
echo "Template width: $nametag_w_mm mm = " . round($nametag_w_mm * $ppm) . " px\n";
echo "Jabatan width: {$jabatanConfig['w']} mm = " . round($jabatanConfig['w'] * $ppm) . " px\n\n";

// Get font
$renderService = new \App\Services\NametagRenderService();
$reflection = new ReflectionClass($renderService);
$resolveFont = $reflection->getMethod('resolveFont');
$resolveFont->setAccessible(true);

$fontKey = $jabatanConfig['font']['key'] ?? 'primary';
$isBold = (bool)($jabatanConfig['font']['bold'] ?? false);
$fontPath = $resolveFont->invoke($renderService, $isBold, $fontKey);

if (!$fontPath || !file_exists($fontPath)) {
    echo "❌ Font file not found: $fontPath\n";
    exit(1);
}

echo "Font: $fontPath\n";
echo "Font Exists: " . (file_exists($fontPath) ? 'YES' : 'NO') . "\n\n";

// Font size in pixels
$fontSizeMm = (float)($jabatanConfig['font']['size'] ?? 2);
$fontSizePx = $fontSizeMm * $ppm * 0.92; // formula dari code
$maxWidthPx = $jabatanConfig['w'] * $ppm;

echo "Font Size: $fontSizeMm mm = " . round($fontSizePx, 2) . " px\n";
echo "Max Width: " . round($maxWidthPx) . " px\n\n";

// Test each jabatan
echo "TEST RESULTS:\n";
echo str_repeat("=", 100) . "\n";

foreach ($testJabatans as $jabatan) {
    echo "\nJabatan: \"$jabatan\"\n";
    echo "  Length: " . strlen($jabatan) . " chars\n";
    
    // Measure with GD
    if (function_exists('imagettfbbox')) {
        $bbox = imagettfbbox($fontSizePx, 0, $fontPath, $jabatan);
        $textWidthPx = abs($bbox[2] - $bbox[0]);
        $textWidthMm = $textWidthPx / $ppm;
        
        echo "  Measured Width: " . round($textWidthPx) . " px (~" . round($textWidthMm, 2) . " mm)\n";
        echo "  Available Width: " . round($maxWidthPx) . " px (~" . round($jabatanConfig['w'], 2) . " mm)\n";
        
        $fitStatus = $textWidthPx <= $maxWidthPx ? '✅ FIT' : '❌ NOT FIT';
        echo "  Fit Status: $fitStatus\n";
        
        if ($textWidthPx > $maxWidthPx) {
            $percentage = round(($textWidthPx / $maxWidthPx) * 100);
            echo "  Overflow: {$percentage}% (terlalu lebar)\n";
        }
        
        // Show remaining space
        $remainingPx = $maxWidthPx - $textWidthPx;
        if ($remainingPx > 0) {
            echo "  Remaining Space: " . round($remainingPx) . " px\n";
        }
        
        // Check if marker+word fits before this (marker = "Ahli ")
        $ahliPrefix = strpos(strtolower($jabatan), 'ahli') !== false ? 'YES' : 'NO';
        echo "  Has 'Ahli': $ahliPrefix\n";
        
        if (stripos($jabatan, 'ahli') !== false) {
            // Simulate what happens with rule
            $marker = "\u{25C7}";
            $simulated = preg_replace('/^(.+?)\s+(Ahli\s+.+)$/iu', '$1' . $marker . '$2', $jabatan);
            if ($simulated !== $jabatan) {
                echo "  Rule Applied: YES (marker inserted)\n";
                echo "  Before Marker: \"" . substr($simulated, 0, strpos($simulated, $marker)) . "\"\n";
                echo "  After Marker: \"" . substr($simulated, strpos($simulated, $marker) + 1) . "\"\n";
                
                // Measure parts
                $beforeMarker = substr($simulated, 0, strpos($simulated, $marker));
                $afterMarker = substr($simulated, strpos($simulated, $marker) + 1);
                
                if (function_exists('imagettfbbox')) {
                    $bbox1 = imagettfbbox($fontSizePx, 0, $fontPath, $beforeMarker);
                    $w1 = abs($bbox1[2] - $bbox1[0]);
                    $bbox2 = imagettfbbox($fontSizePx, 0, $fontPath, $afterMarker);
                    $w2 = abs($bbox2[2] - $bbox2[0]);
                    
                    echo "    Part 1 Width: " . round($w1) . " px (" . ($w1 <= $maxWidthPx ? '✅' : '❌') . ")\n";
                    echo "    Part 2 Width: " . round($w2) . " px (" . ($w2 <= $maxWidthPx ? '✅' : '❌') . ")\n";
                }
            } else {
                echo "  Rule Applied: NO (Ahli di awal atau tidak match)\n";
            }
        }
    }
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "\nSUMMARY:\n";
echo "- Karakter maksimal yg fit 1 baris: ~" . (int)($maxWidthPx / 7) . "-" . (int)($maxWidthPx / 5) . " chars (depends on letter width)\n";
echo "- Rule FORCE split sebelum 'Ahli' HANYA jika 'Ahli' bukan di awal\n";
echo "- Jika 'Analis Kebijakan Ahli Pertama' jadi 2 baris:\n";
echo "  1. Bisa karena RULE force split sebelum 'Ahli'\n";
echo "  2. Atau bisa karena memang tidak muat 1 baris (> $maxWidthPx px)\n";
echo "\n";
