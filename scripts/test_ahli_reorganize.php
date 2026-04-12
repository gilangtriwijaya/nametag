<?php
/**
 * Test logic reorganize Ahli yang baru
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$service = app(\App\Services\NametagRenderService::class);
$ref = new ReflectionClass($service);
$ahliMethod = $ref->getMethod('ensureAhliAtomicAfterWrap');
$ahliMethod->setAccessible(true);

$font = public_path('fonts/OpenSans-Regular.ttf');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ TEST: ensureAhliAtomicAfterWrap() - REORGANIZE LOGIC            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Test case 1: Ahli terpisah
$test1_lines = ["PENGELOLA PENGADAAN BARANG/JASA AHLI", "MUDA"];
$test1_fullText = "PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA";

echo "TEST 1: Ahli di akhir line 1 (separated)\n";
echo "─────────────────────────────────────────\n";
echo "Input lines:\n";
foreach ($test1_lines as $idx => $line) {
    echo "  Line " . ($idx+1) . ": \"$line\"\n";
}
echo "\n";

$result1 = $ahliMethod->invoke($service, $test1_lines, $test1_fullText, 567, $font, 35.0);

echo "Output lines:\n";
foreach ($result1 as $idx => $line) {
    echo "  Line " . ($idx+1) . ": \"$line\"\n";
}

if ($result1[1] === "AHLI MUDA" && strpos($result1[0], "AHLI") === false) {
    echo "\n✓✓✓ SUCCESS: \"Ahli\" berhasil dipindahkan ke line 2\n";
} else {
    echo "\n✗✗✗ FAILED: Output tidak sesuai\n";
}

echo "\n\n";

// Test case 2: Sudah atomic (tidak perlu fix)
$test2_lines = ["PENGELOLA PENGADAAN BARANG/JASA", "AHLI MUDA"];
$test2_fullText = "PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA";

echo "TEST 2: Ahli sudah atomic (tidak perlu fix)\n";
echo "──────────────────────────────────────────────\n";
echo "Input lines:\n";
foreach ($test2_lines as $idx => $line) {
    echo "  Line " . ($idx+1) . ": \"$line\"\n";
}
echo "\n";

$result2 = $ahliMethod->invoke($service, $test2_lines, $test2_fullText, 567, $font, 35.0);

echo "Output lines:\n";
foreach ($result2 as $idx => $line) {
    echo "  Line " . ($idx+1) . ": \"$line\"\n";
}

if ($result2 === $test2_lines) {
    echo "\n✓ NO CHANGE: Sudah atomic, tidak diubah\n";
} else {
    echo "\n✗ UNEXPECTED: Harusnya tidak diubah\n";
}

echo "\n\n";

// Test case 3: Ahli Pertama terpisah
$test3_lines = ["PRANATA KOMPUTER AHLI", "PERTAMA"];
$test3_fullText = "PRANATA KOMPUTER AHLI PERTAMA";

echo "TEST 3: Ahli Pertama (separated)\n";
echo "────────────────────────────────────\n";
echo "Input lines:\n";
foreach ($test3_lines as $idx => $line) {
    echo "  Line " . ($idx+1) . ": \"$line\"\n";
}
echo "\n";

$result3 = $ahliMethod->invoke($service, $test3_lines, $test3_fullText, 567, $font, 35.0);

echo "Output lines:\n";
foreach ($result3 as $idx => $line) {
    echo "  Line " . ($idx+1) . ": \"$line\"\n";
}

if ($result3[1] === "AHLI PERTAMA") {
    echo "\n✓✓✓ SUCCESS: \"Ahli Pertama\" berhasil diatomic-kan\n";
} else {
    echo "\n✗ FAILED\n";
}

echo "\n";
