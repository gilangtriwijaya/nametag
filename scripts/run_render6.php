<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
// Boot the framework to use container
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Services\NametagRenderService;

$e = Employee::find(6);
if (!$e) {
    echo "Employee 6 not found\n";
    exit(2);
}
$svc = $app->make(NametagRenderService::class);
$backTpl = config('nametag.templates.back.background') ?? 'templates/PolosBack.png';
$ok = $svc->renderBack($e, $backTpl);
var_export(['ok' => (bool)$ok]);
echo PHP_EOL;
