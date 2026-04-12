<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = $argv[1] ?? null;
$cleanRel = $argv[2] ?? null;
if (!$id || !$cleanRel) {
    echo "Usage: php scripts/reprocess_single.php <employee_id> <clean_rel_path>\n";
    exit(2);
}

$emp = App\Models\Employee::find($id);
if(!$emp) { echo "Employee not found: $id\n"; exit(3); }

$svc = $app->make(App\Services\EmployeePhotoService::class);
$res = $svc->uploadAndProcess(null, $emp, [], $cleanRel);
var_export(['ok' => $res, 'foto_path' => $emp->foto_path]);
echo "\n";

