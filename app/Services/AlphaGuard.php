<?php

namespace App\Services;

class AlphaGuard
{
    public static function hasMeaningfulAlpha($im, float $minRatio = 0.02): bool
    {
        $w = imagesx($im); $h = imagesy($im);
        $alphaCount = 0;
        $total = $w * $h;

        for ($y=0; $y<$h; $y++) {
            for ($x=0; $x<$w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $a = ($c & 0x7F000000) >> 24;
                if ($a > 0 && $a < 120) $alphaCount++;
            }
        }
        return ($alphaCount / max(1,$total)) >= $minRatio;
    }

    public static function ensureAlpha($im)
    {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        return $im;
    }
}
