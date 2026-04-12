<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$svc = $app->make(App\Services\BackgroundRemovalService::class);
$src = public_path('uploads/employees/03734f45-aeda-4d94-a0cd-88cb569990f6.png');
if (!is_file($src)) {
    echo "source not found: $src\n";
    exit(2);
}
$dst = $svc->clean($src);
if ($dst) {
    echo "ok: $dst\n";
    exit(0);
}
echo "failed\n";
exit(1);
