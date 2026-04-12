<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Employee;
use App\Services\NametagRenderService;

$ids = [7,8,9];
foreach ($ids as $id) {
    echo "--- id: $id ---\n";
    $e = Employee::find($id);
    if (! $e) { echo "missing\n"; continue; }
    $r = new NametagRenderService();
    $okf = $r->renderFront($e, null);
    $okb = $r->renderBack($e, null);
    echo "front: " . ($okf ? 'ok' : 'fail') . " back: " . ($okb ? 'ok' : 'fail') . "\n";
}
