<?php
/**
 * Debug: Trace actual front template jabatan wrapping for employee 16
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$employee = \App\Models\Employee::find(16);
if (!$employee) {
    die("Employee 16 not found\n");
}

$jabatan = $employee->jabatan;
$jabType = $employee->jabatan_type;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ DEBUG: FRONT TEMPLATE JABATAN WRAPPING (Employee 16)           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Employee Data:\n";
printf("  Nama: %s\n", $employee->nama);
printf("  Jabatan: %s\n", $jabatan);
printf("  Type: %s\n", $jabType);
printf("  Should have Ahli fix? %s\n\n", ($jabType === 'FUNGSIONAL' && stripos($jabatan, 'Ahli') !== false) ? 'YES' : 'NO');

// Get config
$cfgFront = config('nametag.templates.front');
$ppm = (300 / 25.4); // pixels per mm @ 300 DPI

// Find jabatan field config
$jabatanCfg = null;
foreach ($cfgFront['texts'] ?? [] as $item) {
    if (($item['key'] ?? null) === 'jabatan') {
        $jabatanCfg = $item;
        break;
    }
}

if (!$jabatanCfg) {
    die("Jabatan config not found!\n");
}

echo "Template Config for Jabatan:\n";
printf("  Width: %dmm = %dpx\n", $jabatanCfg['w'], (int)round($jabatanCfg['w'] * $ppm));
printf("  Font size: %dmm base\n", $jabatanCfg['font']['size']);
printf("  Line height: %.1f\n", $jabatanCfg['line_height']);
printf("  Max wrap: %d lines\n", $jabatanCfg['wrap']);
printf("  Case: %s\n", $jabatanCfg['case'] ?? 'none');
echo "\n";

// Calculate actual font size in px
$baseFontSizeMm = $jabatanCfg['font']['size'];
$baseFontSizePx = $baseFontSizeMm * $ppm * 0.92;
$widthPx = (int)round($jabatanCfg['w'] * $ppm);
$fontPath = public_path('fonts/OpenSans-Regular.ttf');

echo "Calculations:\n";
printf("  Base font: %dmm → %.2fpx\n", $baseFontSizeMm, $baseFontSizePx);
printf("  Width: %dpx\n", $widthPx);
echo "\n";

// Get the service
$service = app(\App\Services\NametagRenderService::class);
$ref = new ReflectionClass($service);

// Call fitWrappedLinesPx to get the actual fitted size
$fitMethod = $ref->getMethod('fitWrappedLinesPx');
$fitMethod->setAccessible(true);
$fittedSize = $fitMethod->invoke($service, $jabatan, $fontPath, $baseFontSizePx, $widthPx, 2);

echo "Font Fitting:\n";
printf("  Fitted size: %.2fpx (for up to 2 lines)\n", $fittedSize);
printf("  Scale: %.2f%%\n\n", ($fittedSize / $baseFontSizePx) * 100);

// Wrap with fitted size  
$wrapMethod = $ref->getMethod('wrapLines');
$wrapMethod->setAccessible(true);
$wrappedLines = $wrapMethod->invoke($service, $jabatan, $widthPx, $fontPath, $fittedSize);

echo "Wrapped Lines (BEFORE post-process):\n";
foreach ($wrappedLines as $idx => $line) {
    printf("  Line %d: \"%s\"\n", $idx + 1, $line);
}
echo "\n";

// Check for Ahli separation
$ahliSeparated = false;
for ($i = 0; $i < count($wrappedLines); $i++) {
    if (preg_match('/\bAhli\s*$/iu', $wrappedLines[$i]) && $i < count($wrappedLines) - 1) {
        $ahliSeparated = true;
        echo "⚠️  Line " . ($i+1) . " ends with 'Ahli' (SEPARATED!)\n\n";
        break;
    }
}

// Apply post-process
$ahliMethod = $ref->getMethod('ensureAhliAtomicAfterWrap');
$ahliMethod->setAccessible(true);
$processedLines = $ahliMethod->invoke($service, $wrappedLines, $jabatan, $widthPx, $fontPath, $fittedSize);

echo "After ensureAhliAtomicAfterWrap():\n";
foreach ($processedLines as $idx => $line) {
    printf("  Line %d: \"%s\"\n", $idx + 1, $line);
}

if ($processedLines === $wrappedLines) {
    echo "\n✓ Lines unchanged (already atomic or no Ahli)\n";
} else {
    echo "\n✓ Lines changed (Ahli was re-processed)\n";
}
