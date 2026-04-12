<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\Employee;

$updated = [];
foreach (Employee::all() as $e) {
    $id = $e->id;
    $front = file_exists(__DIR__ . "/../public/nametag/front/{$id}.png");
    $back  = file_exists(__DIR__ . "/../public/nametag/back/{$id}.png");
    if (($front || $back) && in_array($e->nametag_status, ['queued', 'processing'])) {
        $e->nametag_status = 'ready';
        if (empty($e->nametag_generated_at)) $e->nametag_generated_at = date('Y-m-d H:i:s');
        $e->nametag_error = null;
        $e->save();
        $updated[] = $id;
    }
}
file_put_contents(__DIR__ . "/../storage/logs/reconcile_set_ready.json", json_encode(['updated'=>$updated], JSON_PRETTY_PRINT));
echo "Wrote reconcile_set_ready.json with updated ids: " . json_encode($updated) . "\n";
