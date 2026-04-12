<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Imagick;
use ImagickPixel;

// Note: Imagick PHP extension is optional. This service will fall back to
// ImageMagick CLI (`magick` or `convert`) or to GD functions when Imagick
// is not available. This avoids throwing on construction when the PHP
// extension is not installed (common on minimal servers).

class BgRemoverService
{
    protected string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = public_path('uploads/derived/rembg');
        File::ensureDirectoryExists($this->cacheDir);

        if (!is_writable($this->cacheDir)) {
            throw new RuntimeException('BgRemover cache directory not writable');
        }

        // prefer Imagick if available, but do not require it
        if (!extension_loaded('imagick')) {
            Log::warning('BgRemoverService: Imagick extension not loaded, falling back to CLI/GD');
        }

        // detect ImageMagick CLI binary if present
        $this->magickCmd = null;
        $cmds = ['magick', 'convert'];
        foreach ($cmds as $c) {
            $whichOut = [];
            @exec("command -v {$c} 2>/dev/null", $whichOut, $whichCode);
            if (!empty($whichOut) && $whichCode === 0) {
                $this->magickCmd = trim($whichOut[0]);
                break;
            }
        }

        if (!$this->magickCmd) {
            Log::info('BgRemoverService: ImageMagick CLI not found; will use GD for compositing/postprocessing');
        }

        // detect timeout binary (some distros use busybox without timeout)
        $this->timeoutCmd = null;
        $tout = [];
        @exec('command -v timeout 2>/dev/null', $tout, $tcode);
        if (!empty($tout) && $tcode === 0) {
            $this->timeoutCmd = trim($tout[0]);
        }

        // rembg binary (configurable) — defaults to wrapper we create
        $this->rembgBin = config('photo_pipeline.rembg_bin', '/usr/local/bin/rembg-wrapper');

        // default halo tuning params (can be overridden via config('nametag.rembg'))
        $cfg = config('nametag.rembg', []);
        $this->morphDisk = $cfg['morph_disk'] ?? 2;
        $this->erodeDisk = $cfg['erode_disk'] ?? 1;
        $this->blurRadius = $cfg['blur_radius'] ?? 1;
    }

    /**
     * Full pipeline:
     * original → remove bg → recolor bg → cache
     */
    public function process(string $srcPath, string $bgColor, string $pipelineVersion = 'v1'): string
    {
        if (!is_file($srcPath)) {
            throw new RuntimeException('Source image not found');
        }

        // hash juga tergantung warna bg
        $hash = sha1_file($srcPath) . '_' . ltrim($bgColor, '#') . '_' . $pipelineVersion;
        $dst  = $this->cacheDir . DIRECTORY_SEPARATOR . $hash . '.png';

        if (is_file($dst)) {
            return $dst;
        }

        $lock = fopen($dst . '.lock', 'c');
        flock($lock, LOCK_EX);

        try {
            if (is_file($dst)) {
                return $dst;
            }

            // 1. hapus background (jadi PNG transparan)
            $fgPath = $dst . '.fg.png';
            if (!$this->removeBackground($srcPath, $fgPath)) {
                throw new RuntimeException('Background removal failed');
            }

            // 2. gabungkan dengan background warna jabatan
            $this->applySolidBackground($fgPath, $dst, $bgColor);

            @unlink($fgPath);

            return $dst;

        } catch (Throwable $e) {
            Log::error('bg.pipeline.failed', [
                'src' => $srcPath,
                'bg'  => $bgColor,
                'err' => $e->getMessage(),
            ]);
            throw $e;

        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($dst . '.lock');
        }
    }

    /**
     * Call rembg CLI → output PNG transparan
     */
    protected function removeBackground(string $src, string $dst): bool
    {
        // Use timeout if available to avoid hangs; otherwise run rembg directly.
        $prefix = $this->timeoutCmd ? (escapeshellcmd($this->timeoutCmd) . ' 25s ') : '';
        $bin = escapeshellarg($this->rembgBin);
        $cmd = sprintf('%s%s i %s %s 2>&1', $prefix, $bin, escapeshellarg($src), escapeshellarg($dst));
        exec($cmd, $out, $code);

        $ok = $code === 0 && is_file($dst) && filesize($dst) > 0;
        if ($ok) {
            try { $this->reduceHalo($dst); } catch (Throwable $e) { Log::warning('bg.reduceHalo.failed', ['err'=>$e->getMessage()]); }
            return true;
        }

        Log::warning('BgRemoverService: rembg CLI not available or failed, attempting GD fallback');

        // try GD fallback (chroma-key based) — best-effort
        try {
            $gdOk = $this->removeBackgroundFallbackGD($src, $dst);
            if ($gdOk) {
                try { $this->reduceHalo($dst); } catch (Throwable $e) { Log::warning('bg.reduceHalo.failed', ['err'=>$e->getMessage()]); }
                return true;
            }
        } catch (Throwable $e) {
            Log::error('BgRemoverService.fallback_failed', ['err' => $e->getMessage(), 'src' => $src]);
        }

        return false;
    }

    /**
     * Simple GD-based chroma-key removal fallback.
     * Samples border pixels to estimate background color and makes
     * matching pixels transparent using HSV tolerances from config.
     */
    protected function removeBackgroundFallbackGD(string $src, string $dst): bool
    {
        $info = @getimagesize($src);
        if (!$info) return false;

        switch ($info[2]) {
            case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($src); break;
            case IMAGETYPE_PNG:  $im = @imagecreatefrompng($src); break;
            case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null; break;
            default: $im = null;
        }
        if (!$im) return false;

        $w = imagesx($im); $h = imagesy($im);

        // sample edges to estimate background color
        $samples = [];
        $stepX = max(1, (int)round($w / 20));
        $stepY = max(1, (int)round($h / 20));
        for ($x = 0; $x < $w; $x += $stepX) {
            $samples[] = imagecolorat($im, $x, 0);
            $samples[] = imagecolorat($im, $x, $h - 1);
        }
        for ($y = 0; $y < $h; $y += $stepY) {
            $samples[] = imagecolorat($im, 0, $y);
            $samples[] = imagecolorat($im, $w - 1, $y);
        }

        $r = $g = $b = 0; $count = 0;
        foreach ($samples as $c) {
            $rr = ($c >> 16) & 0xFF;
            $gg = ($c >> 8) & 0xFF;
            $bb = $c & 0xFF;
            $r += $rr; $g += $gg; $b += $bb; $count++;
        }
        if ($count === 0) { imagedestroy($im); return false; }
        $r = (int)round($r / $count);
        $g = (int)round($g / $count);
        $b = (int)round($b / $count);

        [$h0, $s0, $v0] = $this->rgbToHsv($r, $g, $b);

        $hTol = config('photo_bg.h_tolerance', 16);
        $sTol = config('photo_bg.s_tolerance', 0.30);
        $vTol = config('photo_bg.v_tolerance', 0.30);
        $feather = (int) config('photo_bg.feather_px', 1);

        // Build background-match mask (1 = bg-like)
        $bgMask = array_fill(0, $h, array_fill(0, $w, 0));
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $rr = ($c >> 16) & 0xFF;
                $gg = ($c >> 8) & 0xFF;
                $bb = $c & 0xFF;
                [$hh, $ss, $vv] = $this->rgbToHsv($rr, $gg, $bb);

                // hue distance (circular)
                $dh = abs($hh - $h0);
                if ($dh > 180) $dh = 360 - $dh;

                $isBg = ($dh <= $hTol) && (abs($ss - $s0) <= $sTol) && (abs($vv - $v0) <= $vTol);
                $bgMask[$y][$x] = $isBg ? 1 : 0;
            }
        }

        // Seed foreground region via flood fill from center +/- small offsets
        $fgMask = array_fill(0, $h, array_fill(0, $w, 0));
        $seeds = [ [intval($w/2), intval($h/2)] ];
        $offs = [ [0,0], [10,0], [-10,0], [0,10], [0,-10] ];
        foreach ($offs as $o) {
            $seeds[] = [ max(0, min($w-1, intval($w/2 + $o[0]))), max(0, min($h-1, intval($h/2 + $o[1])) ) ];
        }

        $visited = array_fill(0, $h, array_fill(0, $w, 0));
        $stack = [];
        foreach ($seeds as $s) {
            [$sx, $sy] = $s;
            if ($visited[$sy][$sx]) continue;
            // Only start from a pixel that is not background-like
            if ($bgMask[$sy][$sx]) continue;
            $stack[] = [$sx,$sy];
            $visited[$sy][$sx] = 1;
            while (!empty($stack)) {
                [$cx,$cy] = array_pop($stack);
                $fgMask[$cy][$cx] = 1;
                // 4-neighbor
                $nbs = [ [$cx-1,$cy],[$cx+1,$cy],[$cx,$cy-1],[$cx,$cy+1] ];
                foreach ($nbs as $nb) {
                    $nx = $nb[0]; $ny = $nb[1];
                    if ($nx < 0 || $nx >= $w || $ny < 0 || $ny >= $h) continue;
                    if ($visited[$ny][$nx]) continue;
                    // continue the region if neighbor is not bg-like
                    if (!$bgMask[$ny][$nx]) {
                        $visited[$ny][$nx] = 1;
                        $stack[] = [$nx,$ny];
                    }
                }
            }
        }

        // Compose output: pixels in fgMask are kept opaque, others made transparent
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $w, $h, $transparent);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $rr = ($c >> 16) & 0xFF;
                $gg = ($c >> 8) & 0xFF;
                $bb = $c & 0xFF;
                if ($fgMask[$y][$x]) {
                    $col = imagecolorallocatealpha($out, $rr, $gg, $bb, 0);
                } else {
                    $col = imagecolorallocatealpha($out, $rr, $gg, $bb, 127);
                }
                imagesetpixel($out, $x, $y, $col);
            }
        }

        // Feather edges slightly to smooth mask
        if ($feather > 0 && function_exists('imagefilter')) {
            for ($i = 0; $i < $feather; $i++) {
                @imagefilter($out, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }

        @mkdir(dirname($dst), 0777, true);
        $ok = imagepng($out, $dst, 6);

        imagedestroy($im);
        imagedestroy($out);

        return (bool)$ok && is_file($dst);
    }

    protected function rgbToHsv(int $r, int $g, int $b): array
    {
        $r /= 255; $g /= 255; $b /= 255;
        $max = max($r,$g,$b); $min = min($r,$g,$b);
        $d = $max - $min;
        $v = $max;
        $s = $max == 0 ? 0 : $d / $max;
        if ($d == 0) { $h = 0; }
        else {
            switch ($max) {
                case $r: $h = 60 * fmod((($g - $b) / $d), 6); break;
                case $g: $h = 60 * ((($b - $r) / $d) + 2); break;
                default: $h = 60 * ((($r - $g) / $d) + 4); break;
            }
            if ($h < 0) $h += 360;
        }
        return [(float)$h, (float)$s, (float)$v];
    }

    /**
     * Optional ImageMagick-based postprocessing to reduce halo/spill.
     * Uses CLI if available; otherwise no-op.
     */
    protected function reduceHalo(string $path): void
    {
        if (!$this->magickCmd) {
            return;
        }

        $morph = (int)$this->morphDisk;
        $erode = (int)$this->erodeDisk;
        $blur  = (float)$this->blurRadius;

        // Build a conservative command that tries to close small gaps and blur
        // the alpha channel slightly to reduce halo artifacts.
        $cmd = sprintf(
            '%s %s -alpha set -channel A -morphology Close Disk:%d -morphology Erode Disk:%d -blur 0x%s +channel %s',
            escapeshellarg($this->magickCmd),
            escapeshellarg($path),
            $morph,
            $erode,
            escapeshellarg((string)$blur),
            escapeshellarg($path)
        );

        @exec($cmd . ' 2>&1', $out, $code);
        // ignore failures — these are best-effort heuristics
    }

    /**
     * Composite foreground over solid background
     */
    protected function applySolidBackground(string $fgPath, string $outPath, string $hexColor): void
    {
        // Prefer Imagick extension for composition
        if (extension_loaded('imagick')) {
            $fg = new Imagick($fgPath);
            $fg->setImageFormat('png');

            $w = $fg->getImageWidth();
            $h = $fg->getImageHeight();

            // background solid
            $bg = new Imagick();
            $bg->newImage($w, $h, new ImagickPixel($hexColor));
            $bg->setImageFormat('png');

            // composite
            $bg->compositeImage($fg, Imagick::COMPOSITE_OVER, 0, 0);

            $bg->writeImage($outPath);

            $fg->clear();
            $bg->clear();
            return;
        }

        // If Imagick not available, try ImageMagick CLI to composite
        if ($this->magickCmd) {
            // create temporary bg file
            $tmpBg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_' . uniqid() . '.png';
            // create a plain background with same size as foreground
            // read width/height via identify
            $identifyCmd = sprintf('%s -format "%%w %%h" %s', escapeshellarg($this->magickCmd), escapeshellarg($fgPath));
            @exec($identifyCmd . ' 2>/dev/null', $idOut, $idCode);
            if (!empty($idOut) && $idCode === 0) {
                [$w, $h] = array_map('intval', preg_split('/\s+/', trim($idOut[0])) + [0,0]);
                if ($w > 0 && $h > 0) {
                    $createCmd = sprintf('%s -size %dx%d canvas:%s %s', escapeshellarg($this->magickCmd), $w, $h, escapeshellarg($hexColor), escapeshellarg($tmpBg));
                    @exec($createCmd . ' 2>&1', $cOut, $cCode);
                    // composite
                    $compCmd = sprintf('%s composite %s %s %s', escapeshellarg($this->magickCmd), escapeshellarg($fgPath), escapeshellarg($tmpBg), escapeshellarg($outPath));
                    @exec($compCmd . ' 2>&1', $compOut, $compCode);
                    if (is_file($outPath)) {
                        @unlink($tmpBg);
                        return;
                    }
                }
            }
            @unlink($tmpBg);
        }

        // Last resort: GD-based composition
        $fg = @imagecreatefrompng($fgPath);
        if (!$fg) {
            throw new RuntimeException('Failed to load foreground for GD composition');
        }

        $w = imagesx($fg);
        $h = imagesy($fg);

        $bg = imagecreatetruecolor($w, $h);
        // parse hex color
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $bgColor = imagecolorallocate($bg, $r, $g, $b);
        imagefilledrectangle($bg, 0, 0, $w, $h, $bgColor);

        // enable blending and preserve alpha from foreground
        imagealphablending($bg, true);
        imagesavealpha($bg, false);
        imagealphablending($fg, true);
        imagesavealpha($fg, true);

        imagecopy($bg, $fg, 0, 0, 0, 0, $w, $h);

        // write PNG (no alpha needed since background is solid)
        imagepng($bg, $outPath, 9);

        imagedestroy($fg);
        imagedestroy($bg);
    }

    /**
     * Public helper used throughout the app. Produces a transparent PNG
     * by running background removal and caches the result under the
     * service cache dir when $dst is not provided.
     *
     * @param string $src Absolute path to source image
     * @param string|null $dst Optional absolute path to place cleaned PNG
     * @return string|false Absolute path to cleaned PNG on success, false on failure
     */
    public function cleanAndCacheFile(string $src, ?string $dst = null)
    {
        // Deprecated: migrate callers to BackgroundRemovalService/ImageCacheService
        Log::warning('BgRemoverService.cleanAndCacheFile is deprecated; use BackgroundRemovalService and ImageCacheService');
        $bg = app(BackgroundRemovalService::class);
        $cache = app(ImageCacheService::class);

        if (!is_file($src)) return false;

        if ($dst) {
            @mkdir(dirname($dst), 0777, true);
            $res = $bg->clean($src, $dst);
            return $res ?: false;
        }

        $cached = $cache->cachedPathFor($src, 'v1');
        if (is_file($cached)) return $cached;

        $res = $bg->clean($src, $cached);
        return $res ? $cached : false;
    }

}
