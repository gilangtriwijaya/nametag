<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = $argv[1] ?? 6;
use Illuminate\Support\Facades\DB;

$st = DB::table('employees')->where('id', $id)->first();
if (!$st) { echo "no row\n"; exit(2); }
print_r($st);
