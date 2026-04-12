<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emp = App\Models\Employee::find(5);
$svc = $app->make(App\Services\EmployeePhotoService::class);
$clean = 'uploads/employees/clean/5.png';
$filePath = public_path('uploads/employees/1d2b4b53-8d6b-44ce-bb7d-cbe5357ce3d9.png');
$uf = new Illuminate\Http\UploadedFile($filePath, 'orig.png', null, null, true);
$ok = $svc->uploadAndProcess($uf, $emp, [], $clean);
var_export(['ok'=>$ok,'foto_path'=>$emp->foto_path]);
echo "\n";
