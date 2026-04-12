<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = $argv[1] ?? '6';
$e = App\Models\Employee::find($id);
if (!$e) { echo "Employee not found: $id\n"; exit(2); }
print_r([ 'id' => $e->id, 'nametag_status' => $e->nametag_status, 'front' => $e->front_url ?? null, 'back' => $e->back_url ?? null ]);

