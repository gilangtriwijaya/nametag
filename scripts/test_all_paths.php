<?php
/**
 * Comprehensive Test: Compare all 3 render paths
 * PATH 1: Single direct (NametagController@store -> generateSingle)
 * PATH 2: Batch sync (NametagController@run -> batchGenerate)
 * PATH 3: Batch queue (NametagBatchController@dispatch -> RenderSingleNametagJob)
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Employee;
use App\Services\NametagOrchestrator;
use App\Jobs\RenderSingleNametagJob;

echo "=== Test: Comparing all 3 render paths ===\n\n";

$testId = 6; // Employee with Ahli in jabatan
$emp = Employee::find($testId);

if (!$emp || $emp->status_aktif !== 'AKTIF') {
    echo "❌ Employee #$testId not found or not active!\n";
    exit(1);
}

echo "Test Employee: #{$emp->id}\n";
echo "Jabatan: {$emp->jabatan}\n";
echo "Jabatan Type: {$emp->jabatan_type}\n";
echo "Has 'Ahli': " . (stripos($emp->jabatan, 'ahli') !== false ? 'YES' : 'NO') . "\n";
echo "\n";

// === PATH 1: Single Direct ===
echo "=== PATH 1: Single Direct (NametagOrchestrator::generateSingle) ===\n";
$orchestrator = app(NametagOrchestrator::class);
$result1 = $orchestrator->generateSingle($emp, true);
echo "Result: " . ($result1['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: " . ($result1['message'] ?? 'N/A') . "\n";
$file1 = public_path("nametag/front/{$testId}.png");
$time1 = is_file($file1) ? filemtime($file1) : 0;
echo "Front file saved at: " . ($time1 ? date('Y-m-d H:i:s', $time1) : 'NOT FOUND') . "\n";
echo "\n";

// Clear file for next test
if (is_file($file1)) {
    unlink($file1);
    echo "Cleared front file for next test\n";
}
sleep(1);

// === PATH 2: Batch Sync ===
echo "\n=== PATH 2: Batch Sync (NametagOrchestrator::batchGenerate) ===\n";
$batch2Result = $orchestrator->batchGenerate([$emp], ['force' => true]);
echo "Result: ok={$batch2Result['ok']}, fail={$batch2Result['fail']}\n";
$file2 = public_path("nametag/front/{$testId}.png");
$time2 = is_file($file2) ? filemtime($file2) : 0;
echo "Front file saved at: " . ($time2 ? date('Y-m-d H:i:s', $time2) : 'NOT FOUND') . "\n";
echo "\n";

// Clear file for next test
if (is_file($file2)) {
    unlink($file2);
    echo "Cleared front file for next test\n";
}
sleep(1);

// === PATH 3: Batch Queue (synchronously execute job) ===
echo "\n=== PATH 3: Batch Queue (Running RenderSingleNametagJob synchronously) ===\n";
try {
    $renderer = app(\App\Services\NametagRenderService::class);
    $job = new RenderSingleNametagJob($testId, auth()->id() ?? 1, 'test-batch-' . time(), []);
    $job->handle($renderer);
    echo "Job executed successfully\n";
} catch (\Throwable $e) {
    echo "Job execution failed: " . $e->getMessage() . "\n";
}
$file3 = public_path("nametag/front/{$testId}.png");
$time3 = is_file($file3) ? filemtime($file3) : 0;
echo "Front file saved at: " . ($time3 ? date('Y-m-d H:i:s', $time3) : 'NOT FOUND') . "\n";
echo "\n";

echo "=== SUMMARY ===\n";
echo "PATH 1 (single direct): " . ($time1 > 0 ? '✅ File generated' : '❌ No file') . "\n";
echo "PATH 2 (batch sync)   : " . ($time2 > 0 ? '✅ File generated' : '❌ No file') . "\n";
echo "PATH 3 (batch queue)  : " . ($time3 > 0 ? '✅ File generated' : '❌ No file') . "\n";
