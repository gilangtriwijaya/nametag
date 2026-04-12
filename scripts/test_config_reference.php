<?php
/**
 * Test: Check if config array is shared/modified across renderFront calls
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

echo "=== Test: Config array reference behavior ===\n\n";

// Get config via app container
$app = app();
$cfgFront = $app['config']['nametag.templates.front'];

echo "Initial config texts count: " . count($cfgFront['texts']) . "\n";
$initialJabatanWrap = null;
foreach ($cfgFront['texts'] as $i => $it) {
    if (($it['key'] ?? null) === 'jabatan') {
        $initialJabatanWrap = $it['wrap'] ?? 'NOT SET';
        echo "Initial jabatan wrap: $initialJabatanWrap\n";
        echo "Initial `__scaled_px` exists: " . (isset($it['__scaled_px']) ? 'YES' : 'NO') . "\n";
        break;
    }
}

echo "\n--- Simulate renderFront modification ---\n";

// Simulate what renderFront does
$items = $cfgFront['texts'];  // Same as in renderFront line 99
echo "items reference same as config? " . ($items === $cfgFront['texts'] ? 'YES (problem!)' : 'NO (ok)') . "\n";

// Modify items like pre-scaling does
$jabIdx = null;
foreach ($items as $i => $it) {
    if (($it['key'] ?? null) === 'jabatan') {
        $jabIdx = $i;
        break;
    }
}

if ($jabIdx !== null) {
    $items[$jabIdx]['__scaled_px'] = 123.45;
    $items[$jabIdx]['wrap'] = 1;
    echo "Modified jabatan wrap to: 1\n";
    echo "Modified __scaled_px to: 123.45\n";
}

// Check if config changed
$cfgAfter = app()['config']['nametag.templates.front'];
foreach ($cfgAfter['texts'] as $i => $it) {
    if (($it['key'] ?? null) === 'jabatan') {
        echo "\nAfter modification, config jabatan wrap: " . ($it['wrap'] ?? 'NOT SET') . "\n";
        echo "After modification, config __scaled_px: " . ($it['__scaled_px'] ?? 'NOT SET') . "\n";
        if ($it['__scaled_px'] ?? null === 123.45) {
            echo "⚠️  CONFIG WAS MODIFIED! This is the bug!\n";
        } else {
            echo "✓ Config preserved\n";
        }
        break;
    }
}

// Test: Render again and check
echo "\n--- Second renderFront simulation ---\n";
$items2 = $cfgFront['texts'];  // Get fresh reference
foreach ($items2 as $i => $it) {
    if (($it['key'] ?? null) === 'jabatan') {
        echo "Second time, jabatan wrap: " . ($it['wrap'] ?? 'NOT SET') . "\n";
        echo "Second time, __scaled_px: " . ($it['__scaled_px'] ?? 'NOT SET') . "\n";
        break;
    }
}
