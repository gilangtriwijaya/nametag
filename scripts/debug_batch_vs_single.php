<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Employee;
use App\Services\NametagRenderService;

// Test dengan employee 6
$id = 6;
$e = Employee::find($id);

if (!$e) {
    echo "Employee $id not found\n";
    exit(1);
}

echo "\n=== Employee #$id Details ===\n";
echo "ID: " . $e->id . "\n";
echo "Nama: " . $e->nama . "\n";
echo "Jabatan: " . $e->jabatan . "\n";
echo "Jabatan Type: " . $e->jabatan_type . "\n";
echo "Status: " . $e->status_aktif . "\n";
echo "\n";

// Cek apakah FUNGSIONAL
$isFungsional = $e->jabatan_type === 'FUNGSIONAL';
echo "Is FUNGSIONAL? " . ($isFungsional ? 'YES' : 'NO') . "\n";
echo "Ahli post-process akan: " . ($isFungsional ? 'AKTIF' : 'TIDAK AKTIF') . "\n";

// Check lainnya yang memiliki Ahli pada jabatan mereka
echo "\n=== Employees dengan 'Ahli' di jabatan mereka ===\n";
$empWithAhli = Employee::where('jabatan', 'like', '%Ahli%')
    ->where('status_aktif', 'AKTIF')
    ->limit(5)
    ->get();

foreach ($empWithAhli as $emp) {
    echo "ID: {$emp->id}, Jabatan: {$emp->jabatan}, Type: {$emp->jabatan_type}\n";
}
