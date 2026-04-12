<?php
// scripts/finish_all_queued.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$before = DB::table('nametag_batches')->where('status','queued')->count();

$now = date('Y-m-d H:i:s');
$updated = DB::table('nametag_batches')
    ->where('status','queued')
    ->update([
        'status' => 'finished',
        'done' => DB::raw('`total`'),
        'finished_at' => $now,
        'updated_at' => $now,
    ]);

$after = DB::table('nametag_batches')->where('status','queued')->count();

echo "Before queued count: $before\n";
echo "Rows updated: $updated\n";
echo "After queued count: $after\n";

// show a few most recent finished rows that were just updated
$sample = DB::table('nametag_batches')->where('status','finished')->orderBy('updated_at','desc')->limit(10)->get();
foreach ($sample as $r) {
    print_r((array)$r);
    echo "\n";
}
