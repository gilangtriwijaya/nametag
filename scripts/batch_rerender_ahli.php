<?php
/**
 * Batch re-render all employees with AHLI + FUNGSIONAL jabatan
 * to apply the bug fix for back template Ahli rule
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employees = \App\Models\Employee::where('jabatan', 'LIKE', '%AHLI%')
    ->where('jabatan_type', 'FUNGSIONAL')
    ->orderBy('id')
    ->get();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ BATCH RE-RENDER: AHLI + FUNGSIONAL EMPLOYEES                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Found " . count($employees) . " employees to re-render...\n\n";

$orchestrator = app(\App\Services\NametagOrchestrator::class);
$success = 0;
$failed = 0;

foreach ($employees as $idx => $e) {
    $num = $idx + 1;
    printf("[%d/%d] Rendering ID %-4d %s... ", $num, count($employees), $e->id, substr($e->nama, 0, 30));
    
    try {
        $result = $orchestrator->generateSingle($e, true);
        if ($result['success']) {
            echo "✓ OK\n";
            $success++;
        } else {
            echo "✗ " . ($result['reason'] ?? 'Unknown error') . "\n";
            $failed++;
        }
    } catch (\Throwable $ex) {
        echo "✗ " . $ex->getMessage() . "\n";
        $failed++;
    }
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
printf("║ RESULTS: %d success, %d failed out of %d\n", $success, $failed, count($employees));
echo "╚════════════════════════════════════════════════════════════════╝\n";

exit($failed > 0 ? 1 : 0);
