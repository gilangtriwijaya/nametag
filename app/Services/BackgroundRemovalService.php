<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BackgroundRemovalService
{
    protected string $cacheDir;
    protected $magickCmd;
    protected $rembgBin;
    protected $morphDisk;
    protected $erodeDisk;
    protected $blurRadius;

    public function __construct()
    {
        $this->cacheDir = public_path('uploads/rembg');
        @mkdir($this->cacheDir, 0777, true);

        // detect ImageMagick CLI binary if present
        $this->magickCmd = null;
        foreach (['magick', 'convert'] as $c) {
            @exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($c)), $whichOut, $whichCode);
            if (!empty($whichOut) && $whichCode === 0) { $this->magickCmd = trim($whichOut[0]); break; }
        }

        $pp = config('photo_pipeline', []);
        $halo = $pp['halo'] ?? [];
        $this->morphDisk = $halo['morph'] ?? 2;
        $this->erodeDisk = $halo['erode'] ?? 1;
        $this->blurRadius = $halo['blur'] ?? 1;

        $this->rembgBin = $pp['rembg_bin'] ?? '/usr/local/bin/rembg-wrapper';
    }

    /**
     * Run rembg CLI to produce a transparent PNG.
     * If $dst is provided, write there. Returns path on success or false.
     */
    public function clean(string $src, ?string $dst = null, ?string $progressKey = null)
    {
        if (!is_file($src)) return false;

        $tmp = $dst ? ($dst . '.tmp.' . uniqid()) : (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rembg_' . uniqid() . '.png');

        if ($progressKey) {
            try { Cache::put($progressKey, ['status' => 'queued', 'percent' => 3, 'message' => 'queued'], 300); } catch (\Throwable $_) {}
        }

        if ($progressKey) { try { Cache::put($progressKey, ['status' => 'rembg_running', 'percent' => 20, 'message' => 'rembg started'], 300); } catch (\Throwable $_) {} }
        $cmd = sprintf('timeout 25s %s i %s %s 2>&1', escapeshellarg($this->rembgBin), escapeshellarg($src), escapeshellarg($tmp));
        exec($cmd, $out, $code);

        if ($progressKey) { try { Cache::put($progressKey, ['status' => 'rembg_finished_cmd', 'percent' => 60, 'message' => 'rembg finished'], 300); } catch (\Throwable $_) {} }

        $ok = $code === 0 && is_file($tmp) && filesize($tmp) > 0;
        if (!$ok) {
            Log::error('BackgroundRemovalService.rembg_failed', ['src' => $src, 'out' => $out ?? null, 'code' => $code]);
            // GD fallback is opt-in
            $enableGd = config('photo_bg.enable_gd_fallback', false);
            if (!$enableGd) {
                if ($progressKey) { try { Cache::put($progressKey, ['status' => 'failed', 'percent' => 0, 'message' => 'rembg_failed'], 60); } catch (\Throwable $_) {} }
                return false;
            }
            try {
                $gdOk = $this->removeBackgroundFallbackGD($src, $tmp);
                if (!$gdOk) { @unlink($tmp); return false; }
                if ($progressKey) { try { Cache::put($progressKey, ['status' => 'fallback_gd', 'percent' => 50, 'message' => 'gd fallback used'], 120); } catch (\Throwable $_) {} }
            } catch (Throwable $e) {
                Log::error('BackgroundRemovalService.gd_failed', ['err'=>$e->getMessage()]);
                @unlink($tmp);
                if ($progressKey) { try { Cache::put($progressKey, ['status' => 'failed', 'percent' => 0, 'message' => 'gd_failed'], 60); } catch (\Throwable $_) {} }
                return false;
            }
        }

        // best-effort halo reduction
        try {
            if ($progressKey) { try { Cache::put($progressKey, ['status' => 'halo', 'percent' => 80, 'message' => 'halo reduction'], 120); } catch (\Throwable $_) {} }
            $this->reduceHalo($tmp);
        } catch (Throwable $e) { Log::warning('BackgroundRemovalService.reduceHalo_failed', ['err'=>$e->getMessage()]); }

        if ($dst) {
            @mkdir(dirname($dst), 0777, true);
            @rename($tmp, $dst);
            if ($progressKey) { try { Cache::put($progressKey, ['status' => 'done', 'percent' => 100, 'message' => 'done', 'path' => str_replace(public_path(),'',$dst)], 30); } catch (\Throwable $_) {} }
            return is_file($dst) ? $dst : false;
        }

        if ($progressKey) { try { Cache::put($progressKey, ['status' => 'done', 'percent' => 100, 'message' => 'done', 'path' => str_replace(public_path(),'',$tmp)], 30); } catch (\Throwable $_) {} }
        return is_file($tmp) ? $tmp : false;
    }

    protected function reduceHalo(string $path): void
    {
        if (!$this->magickCmd) return;
        $cmd = sprintf('%s %s -alpha set -channel A -morphology Close Disk:%d -morphology Erode Disk:%d -blur 0x%s +channel %s',
            escapeshellarg($this->magickCmd), escapeshellarg($path), (int)$this->morphDisk, (int)$this->erodeDisk, escapeshellarg((string)$this->blurRadius), escapeshellarg($path)
        );
        @exec($cmd . ' 2>&1', $o, $c);
    }

    // Reuse previous GD fallback implementation (kept private here)
    protected function removeBackgroundFallbackGD(string $src, string $dst): bool
    {
        // reuse the existing logic by delegating to BgRemoverService if it exists
        if (class_exists(\App\Services\BgRemoverService::class)) {
            $legacy = new \App\Services\BgRemoverService();
            if (method_exists($legacy, 'removeBackgroundFallbackGD')) {
                return $legacy->removeBackgroundFallbackGD($src, $dst);
            }
        }
        return false;
    }
}
