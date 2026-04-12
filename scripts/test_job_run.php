<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\RenderSingleNametagJob;
use App\Models\Employee;

$id = $argv[1] ?? 6;
$emp = Employee::find($id);
if (!$emp) { echo "Employee not found: $id\n"; exit(2); }

// set to queued to simulate batch initial state
$emp->nametag_status = 'queued';
$emp->save();

echo "Before job, status=" . $emp->nametag_status . "\n";

$job = new RenderSingleNametagJob((int)$emp->id, 1, (string)('testbatch-' . time()), []);

// resolve renderer from container and call handle
$renderer = $app->make(App\Services\NametagRenderService::class);
try {
    $job->handle($renderer);
    echo "Job handle executed.\n";
} catch (Throwable $t) {
    echo "Job threw: " . $t->getMessage() . "\n";
}

// refresh from DB
$emp = Employee::find($id);
echo "After job, status=" . $emp->nametag_status . "\n";
print_r([ 'front_exists' => file_exists(public_path('nametag/front/' . $emp->id . '.png')), 'back_exists' => file_exists(public_path('nametag/back/' . $emp->id . '.png')) ]);

