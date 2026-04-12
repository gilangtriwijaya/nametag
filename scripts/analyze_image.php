<?php
if ($argc < 2) {
    echo "Usage: php analyze_image.php <image.png>\n";
    exit(2);
}
$path = $argv[1];
if (!is_file($path)) { echo "Not found: $path\n"; exit(3); }
$im = @imagecreatefromstring(file_get_contents($path));
if (!$im) { echo "Failed to load: $path\n"; exit(4); }
$w = imagesx($im); $h = imagesy($im);
$tot = $w * $h;
$transCount = 0; $nearWhiteCount = 0; $nearBlackCount = 0;
$sumLuma = 0;
for ($y=0;$y<$h;$y++){
    for ($x=0;$x<$w;$x++){
        $col = imagecolorat($im,$x,$y);
        $a = ($col >> 24) & 0x7F; // 0..127, 127 = fully transparent
        $r = ($col >> 16) & 0xFF;
        $g = ($col >> 8) & 0xFF;
        $b = $col & 0xFF;
        $luma = (0.299*$r + 0.587*$g + 0.114*$b);
        $sumLuma += $luma;
        if ($a > 0) $transCount++;
        if ($luma >= 245) $nearWhiteCount++;
        if ($luma <= 10) $nearBlackCount++;
    }
}
$avgLuma = $sumLuma / max(1,$tot);
printf("File: %s\nSize: %dx%d (pixels=%d)\n", $path, $w, $h, $tot);
printf("Transparent pixels: %d (%.3f%%)\n", $transCount, $transCount/$tot*100);
printf("Near-white pixels (luma>=245): %d (%.3f%%)\n", $nearWhiteCount, $nearWhiteCount/$tot*100);
printf("Near-black pixels (luma<=10): %d (%.3f%%)\n", $nearBlackCount, $nearBlackCount/$tot*100);
printf("Average luma: %.2f\n", $avgLuma);
imagedestroy($im);
