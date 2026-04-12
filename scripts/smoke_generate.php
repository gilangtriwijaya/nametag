<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = $argv[1] ?? null;
if ($id) {
    $emp = App\Models\Employee::find($id);
    if (!$emp) {
        echo "Employee not found: $id\n";
        exit(2);
    }
} else {
    $emp = App\Models\Employee::where('status_aktif', 'AKTIF')->first();
    if (!$emp) {
        echo "No active employee found.\n";
        exit(3);
    }
}

/** @var \App\Services\NametagOrchestrator $orchestrator */
$orchestrator = $app->make(\App\Services\NametagOrchestrator::class);

echo "Generating nametag for employee id={$emp->id} name={$emp->nama}\n";
$emp->nametag_status = 'processing';
try { $emp->save(); } catch (\Throwable $_) {}
$res = $orchestrator->generateSingle($emp, true);

// persist status to DB similar to NametagController::store
try {
    if (!empty($res['success'])) {
        $emp->nametag_status = 'ready';
        $emp->nametag_generated_at = now();
        $emp->nametag_error = null;
        $emp->save();
    } else {
        $emp->nametag_status = 'failed';
        $emp->nametag_error = $res['reason'] ?? ($res['message'] ?? 'failed');
        $emp->save();
    }
} catch (\Throwable $_) {}

echo "Result:\n";
print_r($res);

// show resulting files existence
$frontPath = public_path('nametag/front/' . $emp->id . '.png');
$backPath  = public_path('nametag/back/' . $emp->id . '.png');

echo "Front file: " . ($frontPath && file_exists($frontPath) ? $frontPath : '(missing)') . "\n";
echo "Back file: " . ($backPath && file_exists($backPath) ? $backPath : '(missing)') . "\n";

exit(0);
