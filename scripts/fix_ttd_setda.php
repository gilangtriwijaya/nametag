<?php
$cwd = dirname(__DIR__);
$src = $cwd . '/public/uploads/opd/cleaned/ttd_setda_1767671832.png';
$dest = $cwd . '/public/uploads/opd/ttd_setda.png';
if (!is_file($src)) {
    echo "Source not found: $src\n";
    exit(2);
}
$im = @imagecreatefrompng($src);
if (!$im) {
    echo "Failed to load PNG: $src\n";
    exit(3);
}
$w = imagesx($im);
$h = imagesy($im);
// Ensure truecolor + alpha
$dst = imagecreatetruecolor($w, $h);
imagealphablending($dst, false);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefill($dst, 0, 0, $transparent);
imagecopy($dst, $im, 0, 0, 0, 0, $w, $h);
imagedestroy($im);
// Convert near-white to alpha (threshold configurable)
$threshold = 245; // same as service
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $col = imagecolorat($dst, $x, $y);
        $r = ($col >> 16) & 0xFF;
        $g = ($col >> 8) & 0xFF;
        $b = $col & 0xFF;
        $luma = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
        if ($luma >= $threshold) {
            $alphaFrac = ($luma - $threshold) / max(1, (255 - $threshold));
            $alpha = (int) round(min(1.0, $alphaFrac) * 127);
            if ($r === 255 && $g === 255 && $b === 255) $alpha = 127;
            imagesetpixel($dst, $x, $y, imagecolorallocatealpha($dst, $r, $g, $b, $alpha));
        }
    }
}
// Backup existing dest if present and different
if (is_file($dest)) {
    $same = false;
    if (filesize($dest) === filesize($src)) {
        // cheap check: compare md5
        if (md5_file($dest) === md5_file($src)) $same = true;
    }
    if (!$same) {
        $bak = $dest . '.bak.' . time();
        if (!@rename($dest, $bak)) {
            echo "Warning: could not backup existing dest to $bak\n";
        } else {
            echo "Backed up existing dest to $bak\n";
        }
    }
}
// Save with alpha
imagealphablending($dst, false);
imagesavealpha($dst, true);
$ok = imagepng($dst, $dest, 6);
imagedestroy($dst);
if ($ok) {
    @chmod($dest, 0644);
    echo "Wrote transparent signature to: $dest\n";
    exit(0);
} else {
    echo "Failed to write PNG to $dest\n";
    exit(4);
}
