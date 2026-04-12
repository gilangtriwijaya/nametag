#!/usr/bin/env php
<?php
/**
 * Batch re-render AHLI employees
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

// Get console kernel
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employees = \App\Models\Employee::where('jabatan', 'LIKE', '%AHLI%')
    ->where('jabatan_type', 'FUNGSIONAL')
    ->orderBy('id')
    ->get();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ BATCH RE-RENDER: AHLI + FUNGSIONAL EMPLOYEES                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Total karyawan: " . count($employees) . "\n\n";

$orch = app(\App\Services\NametagOrchestrator::class);
$ok = 0;
$err = 0;

foreach ($employees as $idx => $e) {
    $num = $idx + 1;
    printf("[%2d/%d] ID %-4d %s... ", $num, count($employees), $e->id, substr($e->nama, 0, 25));
    
    try {
        $result = $orch->generateSingle($e, true);
        if ($result['success']) {
            echo "✓\n";
            $ok++;
        } else {
            echo "✗\n";
            $err++;
        }
    } catch (\Throwable $ex) {
        echo "✗ ERROR\n";
        $err++;
    }
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
printf("║ HASIL: %2d SUCCESS, %2d FAILED (Total: %d)                   ║\n", $ok, $err, count($employees));
echo "╚════════════════════════════════════════════════════════════════╝\n";

exit($err > 0 ? 1 : 0);
