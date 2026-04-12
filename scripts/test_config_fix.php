<?php
/**
 * Test FIX: Config reference bug - multiple renders of same employee
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Employee;
use App\Services\NametagRenderService;

// Manually set database for CLI script
try {
    \DB::connection();
} catch (\Throwable $_) {}

$testId = 6;
$emp = Employee::find($testId);

if (!$emp || $emp->status_aktif !== 'AKTIF') {
    echo "❌ Employee #$testId not found or not active!\n";
    exit(1);
}

echo "=== Test: Config Reference Fix ===\n";
echo "Employee #$testId: {$emp->nama}\n";
echo "Jabatan: {$emp->jabatan}\n";
echo "Type: {$emp->jabatan_type}\n\n";

$srv = new NametagRenderService();
$frontFile = public_path("nametag/front/{$testId}.png");

// Delete previous file
if (is_file($frontFile)) {
    unlink($frontFile);
    echo "Cleared previous front file\n";
}

echo "\n=== Simulation: 3 consecutive renders (like batch does) ===\n";

for ($i = 1; $i <= 3; $i++) {
    echo "\nRender #$i:\n";
    
    // Delete file to force re-render
    if (is_file($frontFile)) {
        unlink($frontFile);
    }
    
    $result = $srv->renderFront($emp, null);
    echo "  Result: " . ($result ? '✅ OK' : '❌ FAILED') . "\n";
    
    if (is_file($frontFile)) {
        $size = filesize($frontFile);
        $time = date('H:i:s', filemtime($frontFile));
        echo "  File: Generated at $time ({$size} bytes)\n";
    } else {
        echo "  File: NOT GENERATED\n";
    }
}

echo "\n=== EXPECTED BEHAVIOR ===\n";
echo "All 3 renders should produce identical files (same size, same content)\n";
echo "If sizes significantly differ, config reference bug still exists\n";

// Verify by re-rendering once more
echo "\n=== Bonus: Render again after clearing ===\n";
if (is_file($frontFile)) {
    $beforeSize = filesize($frontFile);
    unlink($frontFile);
}

$result = $srv->renderFront($emp, null);
if (is_file($frontFile)) {
    $afterSize = filesize($frontFile);
    echo "Size match: " . ($beforeSize === $afterSize ? '✅ YES' : '❌ NO') . "\n";
    if ($beforeSize === $afterSize) {
        echo "✅ Config reference fix appears to be working!\n";
    } else {
        echo "⚠️  Size mismatch: $beforeSize vs $afterSize\n";
    }
}
