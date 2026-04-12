<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;

$id = $argv[1] ?? null;
if (!$id) {
    echo "Usage: php scripts/update_employee_photo_path.php <employee_id> <path>\n";
    exit(2);
}
$path = $argv[2] ?? null;
if (!$path) {
    echo "Missing path\n";
    exit(3);
}
$e = Employee::find((int)$id);
if (!$e) {
    echo "Employee $id not found\n";
    exit(4);
}
$e->foto_path = $path;
$e->save();
echo "Updated employee {$e->id} foto_path=" . $e->foto_path . "\n";
