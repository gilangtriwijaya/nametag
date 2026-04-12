<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\RenderNametagBatchJob;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

// pick 2 active employees that are not recently ready
$emps = Employee::where('status_aktif', 'AKTIF')
    ->orderBy('id')
    ->limit(2)
    ->get()
    ->pluck('id')
    ->toArray();

if (count($emps) === 0) { echo "no active employees\n"; exit(2); }

$batchId = 'smokebatch-' . time();
$job = new RenderNametagBatchJob($emps, 1, $batchId, []);

// set them to queued in DB
Employee::whereIn('id', $emps)->update(['nametag_status' => 'queued', 'nametag_error' => null]);

echo "Dispatching batch $batchId for employees: " . implode(',', $emps) . "\n";

// call handle() to enqueue per-employee jobs
$job->handle();

echo "Waiting for worker to process jobs...\n";

// poll DB for statuses for up to 60s
$deadline = time() + 60;
while (time() < $deadline) {
    $rows = DB::table('employees')->whereIn('id', $emps)->select('id','nametag_status')->get();
    $allDone = true;
    foreach ($rows as $r) {
        echo "id={$r->id} status={$r->nametag_status}\n";
        if (!in_array($r->nametag_status, ['ready','failed','skipped'])) $allDone = false;
    }
    if ($allDone) break;
    sleep(2);
}

echo "Final statuses:\n";
$rows = DB::table('employees')->whereIn('id', $emps)->select('id','nametag_status')->get();
foreach ($rows as $r) echo "id={$r->id} status={$r->nametag_status}\n";

