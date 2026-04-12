<?php
/**
 * Check: Apakah pre-scaling effect scaling yang membuat wrap berbeda
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Employee;
use App\Services\NametagRenderService;

echo "=== DEBUG: Pre-scaling Effect pada Batch vs Single ===\n\n";

// Test dengan employee yang punya "Ahli" di jabatan
$ids = [6, 16, 17, 19];  // Employees dengan Ahli di jabatan

foreach ($ids as $id) {
    $e = Employee::find($id);
    if (!$e || $e->status_aktif !== 'AKTIF') continue;
    
    echo "Employee #$id: {$e->jabatan}\n";
    echo "  Jabatan Type: {$e->jabatan_type}\n";
    echo "  Has 'Ahli': " . (stripos($e->jabatan, 'ahli') !== false ? 'YES' : 'NO') . "\n";
    
    // Simulate draw dengan NametagRenderService 
    // Render both front to check if scaled px is applied correctly
    $result = (new NametagRenderService())->renderFront($e, null);
    echo "  Render Result: " . ($result ? 'OK' : 'FAIL') . "\n";
    
    // Check rendered file
    $frontFile = public_path("nametag/front/{$id}.png");
    if (is_file($frontFile)) {
        $mtime = filemtime($frontFile);
        echo "  Front File: Exists (modified " . date('Y-m-d H:i:s', $mtime) . ")\n";
    } else {
        echo "  Front File: NOT FOUND\n";
    }
    
    echo "\n";
}

echo "\nCEK: Apakah rendering result berbeda antara invocation?\n";
echo "Kedua kali menggolkan renderFront untuk employee yang sama,\n";
echo "apakah output kedua jauh berbeda dari yang pertama?\n";

// Test double-render
$e = Employee::find(6);
$srv = new NametagRenderService();

echo "\n=== Double Render Test (Employee #6) ===\n";
$result1 = $srv->renderFront($e, null);
echo "First render: " . ($result1 ? 'SUCCESS' : 'FAILED') . "\n";

// Sleep sedikit
sleep(1);

$result2 = $srv->renderFront($e, null);
echo "Second render: " . ($result2 ? 'SUCCESS' : 'FAILED') . "\n";

$file = public_path("nametag/front/6.png");
echo "File exists: " . (is_file($file) ? 'YES' : 'NO') . "\n";
echo "File size: " . (is_file($file) ? filesize($file) . " bytes" : 'N/A') . "\n";
echo "Last modified: " . (is_file($file) ? date('Y-m-d H:i:s', filemtime($file)) : 'N/A') . "\n";
