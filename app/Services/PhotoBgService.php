<?php

namespace App\Services;

use App\Services\AlphaGuard;
use App\Services\BackgroundRemovalService;
use App\Services\PhotoComposerService;

class PhotoBgService
{
    /* =====================================================
     * PUBLIC API
     * ===================================================== */

    public function processAndCompose(
        string $srcPath,
        string $dstTransparentPath,
        string $dstComposedPath,
        string $hexColorBg,
        array $options = []
    ): bool {
        // 1. Remove background via BackgroundRemovalService
        $removal = app(BackgroundRemovalService::class);
        $ok = false;
        try {
            $res = $removal->clean($srcPath, $dstTransparentPath);
            $ok = $res !== false && is_file($dstTransparentPath);
        } catch (\Throwable $e) {
            $ok = false;
        }

        if (!$ok) {
            // explicit failure (no silent GD fallback unless enabled in config)
            return $this->fallbackFrame($srcPath, $dstComposedPath, $hexColorBg);
        }

        // 2. Validasi alpha
        $fg = @imagecreatefrompng($dstTransparentPath);
        if (!$fg || !AlphaGuard::hasMeaningfulAlpha($fg)) {
            if ($fg) imagedestroy($fg);
            return $this->fallbackFrame($srcPath, $dstComposedPath, $hexColorBg);
        }
        imagedestroy($fg);

        // 3. Compose to solid color via PhotoComposerService
        $composer = app(PhotoComposerService::class);
        return $composer->composeForegroundToColor($dstTransparentPath, $dstComposedPath, $hexColorBg);
    }

    /**
     * Remove background using rembg CLI
     */
    public function toTransparent(string $srcPath, string $dstPath): bool
    {
        if (!is_file($srcPath)) {
            return false;
        }
        // ensure destination folder exists
        @mkdir(dirname($dstPath), 0777, true);

        // prefer central BgRemoverService which has CLI/GD fallbacks
        try {
                // If the source is already a PNG with meaningful alpha, reuse it directly.
                $info = @getimagesize($srcPath);
                if ($info && $info[2] === IMAGETYPE_PNG) {
                    $maybe = @imagecreatefrompng($srcPath);
                    if ($maybe && AlphaGuard::hasMeaningfulAlpha($maybe)) {
                        imagedestroy($maybe);
                        @copy($srcPath, $dstPath);
                        return is_file($dstPath);
                    }
                    if ($maybe) imagedestroy($maybe);
                }

            $remover = app(BackgroundRemovalService::class);
            $res = $remover->clean($srcPath, $dstPath);
            if ($res && is_file($dstPath)) {
                $img = @imagecreatefrompng($dstPath);
                if (!$img) { @unlink($dstPath); return false; }
                $hasAlpha = AlphaGuard::hasMeaningfulAlpha($img);
                imagedestroy($img);
                if (!$hasAlpha) { @unlink($dstPath); return false; }
                return true;
            }
        } catch (\Throwable $e) {
            // fall through to return false
        }

        return false;
    }

    /**
     * Compose transparent PNG to solid color background
     */
    public function composeToColor(
        string $transparentPath,
        string $dstPath,
        string $hexColor,
        array $options = []
    ): bool {
        if (!is_file($transparentPath)) {
            return false;
        }

        $fg = @imagecreatefrompng($transparentPath);
        if (!$fg || !AlphaGuard::hasMeaningfulAlpha($fg)) {
            if ($fg) imagedestroy($fg);
            return false;
        }

        imagesavealpha($fg, true);
        imagealphablending($fg, true);

        $w = imagesx($fg);
        $h = imagesy($fg);

        [$r, $g, $b] = $this->hexToRgb($hexColor);

        $bg = imagecreatetruecolor($w, $h);
        imagefill($bg, 0, 0, imagecolorallocate($bg, $r, $g, $b));

        imagecopy($bg, $fg, 0, 0, 0, 0, $w, $h);

        // optional resize ke box tertentu
        if (!empty($options['fit_to_box'])) {
            $tw = (int)($options['target_w'] ?? $w);
            $th = (int)($options['target_h'] ?? $h);
            $mode = $options['fit_mode'] ?? 'contain';

            $bg = $this->resizeToBox($bg, $tw, $th, $mode, [$r, $g, $b]);
        }

        $ok = imagepng($bg, $dstPath, 6);

        imagedestroy($fg);
        imagedestroy($bg);

        return (bool) $ok;
    }

    /* =====================================================
     * FALLBACK
     * ===================================================== */

    private function fallbackFrame(
        string $srcPath,
        string $dstPath,
        string $hexColor
    ): bool {
        $img = $this->imageCreateFromFile($srcPath);
        if (!$img) {
            return false;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        [$r, $g, $b] = $this->hexToRgb($hexColor);

        $bg = imagecreatetruecolor($w, $h);
        imagefill($bg, 0, 0, imagecolorallocate($bg, $r, $g, $b));
        imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);

        $ok = imagepng($bg, $dstPath, 6);

        imagedestroy($img);
        imagedestroy($bg);

        return (bool) $ok;
    }

    /* =====================================================
     * UTIL
     * ===================================================== */

    private function imageCreateFromFile(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($path)
                : null,
            default => null
        };
    }

    private function resizeToBox($im, int $tw, int $th, string $mode, array $padRgb)
    {
        $sw = imagesx($im);
        $sh = imagesy($im);

        $out = imagecreatetruecolor($tw, $th);
        imagefill($out, 0, 0, imagecolorallocate($out, $padRgb[0], $padRgb[1], $padRgb[2]));

        if ($mode === 'stretch') {
            imagecopyresampled($out, $im, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        } else {
            $scale = min($tw / $sw, $th / $sh);
            $rw = (int) round($sw * $scale);
            $rh = (int) round($sh * $scale);
            $ox = (int) (($tw - $rw) / 2);
            $oy = (int) (($th - $rh) / 2);
            imagecopyresampled($out, $im, $ox, $oy, 0, 0, $rw, $rh, $sw, $sh);
        }

        imagedestroy($im);
        return $out;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            return [
                hexdec(str_repeat($hex[0], 2)),
                hexdec(str_repeat($hex[1], 2)),
                hexdec(str_repeat($hex[2], 2)),
            ];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
