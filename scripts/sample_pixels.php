<?php
$path = $argv[1] ?? null;
if (!$path) { echo "Usage: php scripts/sample_pixels.php <path>\n"; exit(2); }
$im = imagecreatefrompng($path);
$w = imagesx($im); $h = imagesy($im);
echo "size={$w}x{$h}\n";
$coords = [[0,0], [$w-1,0], [0,$h-1], [$w-1,$h-1], [intval($w/2), intval($h/2)]];
foreach ($coords as $c) {
  $x = $c[0]; $y = $c[1];
  $cval = imagecolorat($im, $x, $y);
  $r = ($cval >> 16) & 0xFF;
  $g = ($cval >> 8) & 0xFF;
  $b = $cval & 0xFF;
  echo "coord({$x},{$y})={$r},{$g},{$b}\n";
}
imagedestroy($im);
