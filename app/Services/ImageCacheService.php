<?php

namespace App\Services;

use Illuminate\Support\Str;

class ImageCacheService
{
    protected string $cacheDir;

    public function __construct()
    {
        // keep original root for backward compatibility, but prefer strict separation
        $this->origDir = public_path('uploads/originals/employees');
        $this->derivedDir = public_path('uploads/derived/employees');
        @mkdir($this->origDir, 0777, true);
        @mkdir($this->derivedDir, 0777, true);
    }

    /**
     * Return a derived cache path for a source file.
     * If $jobKey is provided, the image is placed under job-<slug> folder.
     */
    public function cachedPathFor(string $src, string $pipelineVersion = 'v1', ?string $jobKey = null) : string
    {
        $fileHash = @sha1_file($src);
        $mtime = is_file($src) ? (string) @filemtime($src) : (string) time();
        if ($fileHash) {
            $hash = $fileHash . '_' . $mtime;
        } else {
            $hash = sha1(uniqid((string) $src . $mtime, true));
        }
        $name = $hash . '_' . $pipelineVersion . '.png';
        if ($jobKey) {
            $slug = Str::slug($jobKey, '-') ?: 'job';
            $dir = $this->derivedDir . DIRECTORY_SEPARATOR . 'job-' . $slug;
        } else {
            $dir = $this->derivedDir . DIRECTORY_SEPARATOR . 'misc';
        }
        @mkdir($dir, 0777, true);
        return $dir . DIRECTORY_SEPARATOR . $name;
    }

    public function lockPath(string $dst): string
    {
        return $dst . '.lock';
    }

    public function withLock(string $dst, callable $cb)
    {
        $lock = fopen($this->lockPath($dst), 'c');
        if (!$lock) return $cb();
        flock($lock, LOCK_EX);
        try {
            return $cb();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($this->lockPath($dst));
        }
    }

    /**
     * Write JSON manifest alongside an image path (image.png -> image.png.json)
     */
    public function writeManifest(string $imagePath, array $meta): void
    {
        try {
            $jsonPath = $imagePath . '.json';
            @file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            // don't break pipeline on manifest write failure
        }
    }
}
