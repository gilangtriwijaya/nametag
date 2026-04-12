<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Support\NametagData;

$e = Employee::where('nama','like','%Yohan%')->orWhere('nama','like','%YOHAN%')->first();
if (!$e) {
    echo "employee not found\n";
    exit(0);
}
echo "id: {$e->id} name: {$e->nama}\n";
$cfg = config('nametag.templates.front');
$tplPath = public_path($cfg['background']);
if (!is_file($tplPath)) {
    echo "template missing: $tplPath\n";
    exit(0);
}
$tpl = imagecreatefrompng($tplPath);
$ppm = imagesx($tpl) / ($cfg['size_mm']['w'] ?? 141.12);
imagedestroy($tpl);
echo "ppm: $ppm\n";
$items = $cfg['texts'];
$mapIdx = [];
foreach ($items as $i => $it) {
    if (in_array($it['key'] ?? null, ['nama','nip','jabatan'], true)) $mapIdx[$it['key']] = $i;
}
$textMap = NametagData::buildFront($e);
// Simulate service's NIP label merging: find nip_label and merge label + value
$nipLabelIdx = null;
foreach ($items as $i => $it) {
    if (($it['key'] ?? null) === 'nip_label') { $nipLabelIdx = $i; break; }
}
$nipValue = (string)($textMap['nip'] ?? '');
if ($nipValue !== '') {
    $labelText = 'NIP.';
    if ($nipLabelIdx !== null) {
        $rawLabel = $items[$nipLabelIdx]['text'] ?? null;
        if (is_string($rawLabel) && trim($rawLabel) !== '') $labelText = trim($rawLabel);
        $items[$nipLabelIdx]['text'] = null;
        $items[$nipLabelIdx]['flow'] = false;
    }
    $textMap['nip'] = trim($labelText . ' ' . $nipValue);
}
$getBasePx = function($it) use ($ppm) {
    $mm = (float)($it['font']['size'] ?? 5.5);
    return $mm * $ppm * 0.92;
};
$itNama = $items[$mapIdx['nama']];
$itJab = $items[$mapIdx['jabatan']];
$itNip = isset($mapIdx['nip']) ? $items[$mapIdx['nip']] : null;
$baseNamaPx = $getBasePx($itNama);
$baseJabPx = $getBasePx($itJab);
$baseNipPx = $itNip ? $getBasePx($itNip) : $baseNamaPx;
echo "basePx nama:$baseNamaPx nip:$baseNipPx jab:$baseJabPx\n";
$font = config('nametag.font.regular');
if (is_array($font)) $font = $font['regular'] ?? array_values($font)[0] ?? null;
if (!$font) $font = public_path('fonts/OpenSans-Regular.ttf');
echo "font: $font\n";

$s = app(\App\Services\NametagRenderService::class);
$rm = new ReflectionMethod($s, 'wrapLines');
$rm->setAccessible(true);

$wNama = (int)round(($itNama['w'] ?? 9999) * $ppm);
$wJab  = (int)round(($itJab['w'] ?? 9999) * $ppm);
$wNip  = $itNip ? (int)round(($itNip['w'] ?? ($itNama['w'] ?? 9999)) * $ppm) : null;

$namaVal = (string)($textMap['nama'] ?? '');
$nipVal  = (string)($textMap['nip'] ?? '');
$jabVal  = (string)($textMap['jabatan'] ?? '');

echo "values:\nNAMA: $namaVal\nNIP: $nipVal\nJAB: $jabVal\n";

$linesNama = $rm->invokeArgs($s, [$namaVal, $wNama, $font, $baseNamaPx]);
$linesNip  = $rm->invokeArgs($s, [$nipVal, $wNip, $font, $baseNipPx]);
$linesJab  = $rm->invokeArgs($s, [$jabVal, $wJab, $font, $baseJabPx]);

echo "lines count - nama:".count($linesNama)." nip:".(is_array($linesNip)?count($linesNip):'null')." jab:".count($linesJab)."\n";
echo "linesNama:".json_encode($linesNama)."\n";
echo "linesNip:".json_encode($linesNip)."\n";
echo "linesJab:".json_encode($linesJab)."\n";

// Reproduce pre-scaling + special layout tweak from NametagRenderService
$getBasePx = function($it) use ($ppm) { $mm = (float)($it['font']['size'] ?? 5.5); return $mm * $ppm * 0.92; };
$reflectResolve = new ReflectionMethod($s, 'resolveFont');
$reflectResolve->setAccessible(true);
$fontNamaPath = $reflectResolve->invokeArgs($s, [(bool)($itNama['font']['bold'] ?? false), $itNama['font']['key'] ?? null]) ?: $reflectResolve->invokeArgs($s, [false, $itNama['font']['key'] ?? null]);
$fontJabPath  = $reflectResolve->invokeArgs($s, [(bool)($itJab['font']['bold'] ?? false), $itJab['font']['key'] ?? null]) ?: $reflectResolve->invokeArgs($s, [false, $itJab['font']['key'] ?? null]);

$baseNamaPx = $getBasePx($itNama);
$baseJabPx  = $getBasePx($itJab);
$baseNipPx  = $itNip ? $getBasePx($itNip) : $baseNamaPx;

$wNama = (int)round(($itNama['w'] ?? 9999) * $ppm);
$wJab  = (int)round(($itJab['w'] ?? 9999) * $ppm);

 $linesNama2 = $rm->invokeArgs($s, [$namaVal, $wNama, $fontNamaPath, $baseNamaPx]);
 $linesJab2  = $rm->invokeArgs($s, [$jabVal, $wJab, $fontJabPath, $baseJabPx]);

// Show original y before possible adjustment
$origNipY = (float)($items[$mapIdx['nip']]['y'] ?? 0);
$origJabY = (float)($items[$mapIdx['jabatan']]['y'] ?? 0);

// Only check jabatan fit per new rule
$fitsOneLine = (count($linesJab2) === 1);
if ($fitsOneLine) {
    if ($itNip && isset($items[$mapIdx['nip']])) {
        $items[$mapIdx['nip']]['y'] = $origNipY + 1;
    }
    $items[$mapIdx['jabatan']]['y'] = $origJabY + 2;
}

echo "fitsOneLine: ".($fitsOneLine? 'yes':'no')."\n";
echo "counts used - nama:".count($linesNama2)." nip:".(isset($linesNip2)?count($linesNip2):'null')." jab:".count($linesJab2)."\n";
echo "linesNama2:".json_encode($linesNama2)."\n";
echo "linesNip2:".(isset($linesNip2)?json_encode($linesNip2):'null')."\n";
echo "linesJab2:".json_encode($linesJab2)."\n";

// Show original and adjusted y (mm)
$origNipY = (float)($items[$mapIdx['nip']]['y'] ?? 0);
$origJabY = (float)($items[$mapIdx['jabatan']]['y'] ?? 0);
if ($fitsOneLine) {
    if ($itNip && isset($items[$mapIdx['nip']])) {
        $items[$mapIdx['nip']]['y'] = $origNipY + 1;
    }
    $items[$mapIdx['jabatan']]['y'] = $origJabY + 2;
}
echo "nip y mm before:$origNipY after:".($items[$mapIdx['nip']]['y'] ?? 'n/a')."\n";
echo "jab y mm before:$origJabY after:".($items[$mapIdx['jabatan']]['y'] ?? 'n/a')."\n";
