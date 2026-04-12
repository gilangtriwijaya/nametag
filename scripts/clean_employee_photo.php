<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Services\BackgroundRemovalService;

$argv = $_SERVER['argv'] ?? [];
if (count($argv) < 2) {
    echo "Usage: php scripts/clean_employee_photo.php <employee_id>\n";
    exit(2);
}
$id = (int)$argv[1];
$e = Employee::find($id);
if (!$e) {
    echo "Employee $id not found\n";
    exit(3);
}
// Determine source photo: prefer foto_path, else pick latest file in uploads/employees that contains uuid-like name
$src = null;
if ($e->foto_path) {
    $p = public_path(ltrim($e->foto_path, '/'));
    if (is_file($p)) $src = $p;
}
if (!$src) {
    $dir = public_path('uploads/employees');
    if (is_dir($dir)) {
        $files = glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
        // choose newest file (mtime desc)
        usort($files, fn($a,$b)=> filemtime($b) <=> filemtime($a));
        if (!empty($files)) {
            $src = $files[0];
        }
    }
}
if (!$src || !is_file($src)) {
    echo "Source photo not found for employee $id\n";
    exit(4);
}
$dstDir = public_path('uploads/employees/clean');
if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
$dst = $dstDir . '/' . $id . '.png';
$bg = $app->make(App\Services\BackgroundRemovalService::class);
$cleaned = $bg->clean($src, $dst);
if ($cleaned) {
    echo "Cleaned photo written to: $cleaned\n";
    // re-render back
    $svc = $app->make(App\Services\NametagRenderService::class);
    $ok = $svc->renderBack($e, config('nametag.templates.back.background'));
    echo "Render back: " . ($ok? 'ok' : 'fail') . "\n";
    exit($ok ? 0 : 5);
} else {
    echo "Failed to clean source $src\n";
    exit(6);
}
