<?php

namespace App\Support;

class NametagPainter
{
    /**
     * Ubah HEX (#rrggbb / #rgb) -> array [r,g,b].
     */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat($hex[0], 2));
            $g = hexdec(str_repeat($hex[1], 2));
            $b = hexdec(str_repeat($hex[2], 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }

    /**
     * (Opsional) hitung lebar teks TTF; berguna bila nanti mau auto-wrap/align.
     */
    public static function textBoxWidth(string $text, float $size, string $fontPath): float
    {
        $box = imagettfbbox($size, 0, $fontPath, $text);
        if (!$box) return 0.0;
        // xmin = min(x0,x2,x4,x6), xmax = max(x1,x3,x5,x7)
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $xe = [$box[1], $box[3], $box[5], $box[7]];
        return max(array_merge($xs, $xe)) - min(array_merge($xs, $xe));
    }
}
