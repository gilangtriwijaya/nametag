<?php
/**
 * Test Batch Render: Simulating what batch controller does
 * This tests if config reference fix works correctly
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Employee;
use App\Services\NametagOrchestrator;

echo "=== Test Batch Render dengan Config Reference Fix ===\n\n";

$testIds = [6, 16, 17, 19];  // Employees dengan Ahli di jabatan

echo "Staff dengan 'Ahli' di jabatan:\n";
foreach ($testIds as $id) {
    $emp = Employee::find($id);
    if ($emp && $emp->status_aktif === 'AKTIF') {
        echo "  #{$id}: {$emp->nama} - {$emp->jabatan}\n";
    }
}

echo "\n=== Batch Render (simulating NametagController@run) ===\n";

// Get employees
$employees = Employee::whereIn('id', $testIds)
    ->where('status_aktif', 'AKTIF')
    ->get();

echo "Found " . $employees->count() . " employees\n";

// Use orchestrator like NametagController@run does
$orchestrator = app(NametagOrchestrator::class);
$result = $orchestrator->batchGenerate($employees, ['force' => true]);

echo "\nBatch Result:\n";
echo "  OK: " . $result['ok'] . "\n";
echo "  FAIL: " . $result['fail'] . "\n";
echo "  Total: " . $result['total'] . "\n";

// Check file sizes to verify consistency
echo "\n=== File Verification ===\n";
foreach ($testIds as $id) {
    $file = public_path("nametag/front/{$id}.png");
    if (is_file($file)) {
        $size = filesize($file);
        echo "#{$id}: $size bytes\n";
    } else {
        echo "#{$id}: NOT FOUND\n";
    }
}

echo "\n✅ Batch test complete\n";
echo "Check: Are all 'Ahli' jobs rendering without errors? (FAIL should be 0)\n";
