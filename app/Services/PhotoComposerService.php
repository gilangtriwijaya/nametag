<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickPixel;

class PhotoComposerService
{
    protected $magickCmd;

    public function __construct()
    {
        $this->magickCmd = null;
        foreach (['magick','convert'] as $c) {
            @exec("command -v {$c} 2>/dev/null", $o, $cCode);
            if (!empty($o) && $cCode === 0) { $this->magickCmd = trim($o[0]); break; }
        }
    }

    public function composeForegroundToColor(string $fgPath, string $outPath, string $hexColor): bool
    {
        if (!is_file($fgPath)) return false;

        // Prefer Imagick
        if (extension_loaded('imagick')) {
            try {
                $fg = new Imagick($fgPath);
                $w = $fg->getImageWidth(); $h = $fg->getImageHeight();
                $bg = new Imagick();
                $bg->newImage($w, $h, new ImagickPixel($hexColor));
                $bg->setImageFormat('png');
                $bg->compositeImage($fg, Imagick::COMPOSITE_OVER, 0, 0);
                $bg->writeImage($outPath);
                $fg->clear(); $bg->clear();
                return is_file($outPath);
            } catch (\Throwable $e) {
                Log::warning('PhotoComposerService.imagick_failed', ['err'=>$e->getMessage()]);
            }
        }

        // Try ImageMagick CLI
        if ($this->magickCmd) {
            $identifyCmd = sprintf('%s -format "%%w %%h" %s', escapeshellarg($this->magickCmd), escapeshellarg($fgPath));
            @exec($identifyCmd . ' 2>/dev/null', $idOut, $idCode);
            if (!empty($idOut) && $idCode === 0) {
                [$w,$h] = array_map('intval', preg_split('/\s+/', trim($idOut[0])) + [0,0]);
                if ($w > 0 && $h > 0) {
                    $tmpBg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_' . uniqid() . '.png';
                    $createCmd = sprintf('%s -size %dx%d canvas:%s %s', escapeshellarg($this->magickCmd), $w, $h, escapeshellarg($hexColor), escapeshellarg($tmpBg));
                    @exec($createCmd . ' 2>&1');
                    $comp = sprintf('%s composite %s %s %s', escapeshellarg($this->magickCmd), escapeshellarg($fgPath), escapeshellarg($tmpBg), escapeshellarg($outPath));
                    @exec($comp . ' 2>&1');
                    if (is_file($outPath)) { @unlink($tmpBg); return true; }
                    @unlink($tmpBg);
                }
            }
        }

        // Fallback to GD
        $fg = @imagecreatefrompng($fgPath);
        if (!$fg) return false;
        $w = imagesx($fg); $h = imagesy($fg);
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
        $bg = imagecreatetruecolor($w, $h);
        $bgColor = imagecolorallocate($bg, $r, $g, $b);
        imagefilledrectangle($bg, 0, 0, $w, $h, $bgColor);
        imagealphablending($bg, true);
        imagesavealpha($bg, false);
        imagealphablending($fg, true);
        imagesavealpha($fg, true);
        imagecopy($bg, $fg, 0, 0, 0, 0, $w, $h);
        $ok = imagepng($bg, $outPath, 9);
        imagedestroy($fg); imagedestroy($bg);
        return (bool)$ok;
    }
}
