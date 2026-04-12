<?php
/**
 * Test script untuk verify Ahli jabatan rule
 */
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Employee;
use App\Services\NametagRenderService;

// Test data
$testCases = [
    [
        'nip' => '199601231234561234',
        'nama' => 'Budi Santoso',
        'jabatan' => 'Ahli Keuangan Daerah',
        'jabatan_type' => 'FUNGSIONAL',
        'desc' => 'FUNGSIONAL dengan Ahli di awal'
    ],
    [
        'nip' => '199501201234561234',
        'nama' => 'Siti Nurhaliza',
        'jabatan' => 'Ahli Kebijakan Publik Bidang Administrasi Pemerintahan',
        'jabatan_type' => 'FUNGSIONAL',
        'desc' => 'FUNGSIONAL dengan Ahli panjang di awal'
    ],
    [
        'nip' => '199401101234561234',
        'nama' => 'Ahmad Hidayat',
        'jabatan' => 'Kepala Bidang Ahli Keuangan Daerah',
        'jabatan_type' => 'FUNGSIONAL',
        'desc' => 'FUNGSIONAL dengan Ahli di tengah'
    ],
    [
        'nip' => '199301151234561234',
        'nama' => 'Rani Wijaya',
        'jabatan' => 'Kepala Bagian Perencanaan Ahli Statistik Senior Bidang Ekonomi',
        'jabatan_type' => 'FUNGSIONAL',
        'desc' => 'FUNGSIONAL dengan Ahli panjang di tengah'
    ],
    [
        'nip' => '199201121234561234',
        'nama' => 'Kepala Bidang Keuangan',
        'jabatan' => 'Kepala Bidang Keuangan',
        'jabatan_type' => 'FUNGSIONAL',
        'desc' => 'FUNGSIONAL tanpa Ahli'
    ],
    [
        'nip' => '199101051234561234',
        'nama' => 'Lina Wijaya',
        'jabatan' => 'Ahli Statistik Senior',
        'jabatan_type' => 'PENGAWAS',
        'desc' => 'PENGAWAS dengan Ahli di awal (rule tidak apply)'
    ],
];

echo "========== TEST AHLI RULE ==========\n\n";

// Mock the prepareAhliJabatan function output
$renderService = new NametagRenderService();
$reflection = new ReflectionClass($renderService);

// Get private method
$prepareMethod = $reflection->getMethod('prepareAhliJabatan');
$prepareMethod->setAccessible(true);

foreach ($testCases as $tc) {
    echo "TEST: {$tc['desc']}\n";
    echo "  Jabatan Type: {$tc['jabatan_type']}\n";
    echo "  Original: {$tc['jabatan']}\n";
    
    // Simulate the rule
    $jabatanType = $tc['jabatan_type'];
    $jabatanText = $tc['jabatan'];
    
    if ($jabatanType === 'FUNGSIONAL' && stripos($jabatanText, 'Ahli') !== false) {
        // Rule applies
        $prepared = $prepareMethod->invoke($renderService, $jabatanText);
        $marker = "\u{25C7}";
        $hasMarker = strpos($prepared, $marker) !== false;
        echo "  Rule Applied: YES\n";
        echo "  Marker Present: " . ($hasMarker ? 'YES' : 'NO') . "\n";
        echo "  Prepared (marker shown as [◇]): " . str_replace($marker, '[◇]', $prepared) . "\n";
    } else {
        echo "  Rule Applied: NO\n";
    }
    echo "\n";
}

echo "========== TEST COMPLETE ==========\n";

