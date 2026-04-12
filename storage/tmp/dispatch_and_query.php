<?php
// One-off script to create a test NametagBatch, dispatch dispatcher job, and call queued endpoint.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Models\Employee;
use App\Models\NametagBatch;
use App\Jobs\RenderNametagBatchJob;
use App\Http\Controllers\NametagBatchController;

// choose first 3 employees for test
$ids = Employee::whereNotNull('nama')->limit(3)->pluck('id')->map(function($v){return (int)$v;})->all();
if (empty($ids)) {
    echo "no employees found\n";
    exit(0);
}

$batchId = (string) Str::uuid();
try {
    $nb = NametagBatch::create([
        'id' => $batchId,
        'user_id' => 1,
        'opd_id' => null,
        'opd_unit_id' => null,
        'employee_ids' => $ids,
        'total' => count($ids),
        'done' => 0,
        'fail' => 0,
        'skipped' => 0,
        'status' => 'queued',
        'started_at' => now(),
    ]);
    echo "created batch: {$batchId}\n";
} catch (Throwable $e) {
    echo "failed to create batch: " . $e->getMessage() . "\n";
}

// dispatch dispatcher job on nametag queue
try {
    RenderNametagBatchJob::dispatch($ids, 1, $batchId, [])->onQueue('nametag');
    echo "dispatched RenderNametagBatchJob for batch {$batchId}\n";
} catch (Throwable $e) {
    echo "failed to dispatch: " . $e->getMessage() . "\n";
}

// Simulate calling queued endpoint as user 1
try {
    Auth::loginUsingId(1);
    $c = new NametagBatchController();
    $resp = $c->queued(new Request());
    if (is_object($resp) && method_exists($resp, 'getContent')) {
        echo "queued endpoint returned:\n";
        echo $resp->getContent() . "\n";
    } else {
        echo "queued endpoint did not return a response object\n";
    }
} catch (Throwable $e) {
    echo "failed to call queued: " . $e->getMessage() . "\n";
}

return 0;
