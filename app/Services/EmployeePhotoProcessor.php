<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EmployeePhotoProcessor
{
    protected BackgroundRemovalService $removal;
    protected PhotoComposerService $composer;
    protected ImageCacheService $cache;

    public function __construct(BackgroundRemovalService $removal, PhotoComposerService $composer, ImageCacheService $cache)
    {
        $this->removal = $removal;
        $this->composer = $composer;
        $this->cache = $cache;
    }

    /**
     * Process a source image for given jabatan color.
     * Returns composed final path on success or false.
     */
    public function process(string $src, string $hexColor, string $pipelineVersion = 'v1', ?string $jobKey = null)
    {
        if (!is_file($src)) return false;

        $cached = $this->cache->cachedPathFor($src, $pipelineVersion, $jobKey);

        return $this->cache->withLock($cached, function() use ($src, $cached, $hexColor) {
            if (is_file($cached)) return $cached;

            $tmpFg = $cached . '.fg.png';
            $cleaned = $this->removal->clean($src, $tmpFg);
            if (!$cleaned || !is_file($tmpFg)) {
                Log::error('employee.photo.process.removal_failed', ['src'=>$src]);
                return false;
            }

            $ok = $this->composer->composeForegroundToColor($tmpFg, $cached, $hexColor);
            @unlink($tmpFg);
            if ($ok) {
                // write minimal pipeline manifest
                $meta = [
                    'pipeline' => 'employee_photo',
                    'version'  => config('photo_pipeline.version', '1.0.0'),
                    'bg'       => $hexColor,
                    'rembg'    => config('photo_pipeline.rembg_model', 'u2net'),
                    'halo'     => config('photo_pipeline.halo', []),
                    'created_at' => gmdate('c'),
                ];
                $this->cache->writeManifest($cached, $meta);
            }
            return $ok ? $cached : false;
        });
    }
}
