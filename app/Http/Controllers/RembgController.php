<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\BackgroundRemovalService;
use App\Services\ImageCacheService;

class RembgController extends Controller
{
    protected BackgroundRemovalService $bg;
    protected ImageCacheService $cache;

    public function __construct(BackgroundRemovalService $bg, ImageCacheService $cache)
    {
        $this->bg = $bg;
        $this->cache = $cache;
    }

    /**
     * POST /rembg/clean-employee
     * body: { "filename": "03734f45-aeda-4d94-a0cd-88cb569990f6.png" }
     * Returns JSON { ok: true, url: '/uploads/opd/cleaned/...' } or { ok: false, error: '...' }
     */
    public function cleanEmployee(Request $req)
    {
        Log::info('rembg.cleanEmployee start', [
            'ip' => $req->ip(),
            'filename' => $req->input('filename')
        ]);

        $req->validate(['filename' => 'required|string']);
        $filename = basename($req->input('filename'));
        $src = public_path('uploads/employees/' . $filename);
        if (!is_file($src)) {
            Log::warning('rembg.cleanEmployee source_not_found', ['src' => $src]);
            return response()->json(['ok' => false, 'error' => 'source_not_found'], 404);
        }

        try {
            $pipeline = config('photo_pipeline.version', config('photo_bg.pipeline_version', 'v1'));
            $dst = $this->cache->cachedPathFor($src, $pipeline, 'clean');
            $progressKey = $req->input('progress_key');
            $res = $this->bg->clean($src, $dst, $progressKey ?? null);
            if ($res && is_file($dst)) {
                $this->cache->writeManifest($dst, [
                    'pipeline' => 'employee_photo',
                    'version' => $pipeline,
                    'rembg' => config('photo_pipeline.rembg_model'),
                    'created_at' => gmdate('c'),
                ]);
            }
            if ($res === false) {
                Log::error('rembg.cleanEmployee processing_failed', ['src' => $src]);
                return response()->json(['ok' => false, 'error' => 'processing_failed'], 500);
            }
            // convert to absolute asset URL (respects subdirectory deployment)
            $rel = str_replace(public_path(), '', $dst);
            $rel = ltrim($rel, '/\\');
            $url = asset($rel);
            Log::info('rembg.cleanEmployee ok', ['url' => $url, 'rel' => $rel]);
            return response()->json(['ok' => true, 'url' => $url, 'path' => $rel]);
        } catch (\Throwable $e) {
            Log::error('rembg.cleanEmployee failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'exception'], 500);
        }
    }

    /**
     * Internal endpoint for loopback calls (bypasses CSRF and throttles)
     * Only accepts requests from 127.0.0.1 or ::1 for safety.
     */
    public function cleanEmployeeInternal(Request $req)
    {
        $ip = $req->ip();
        if (!in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'])) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        Log::info('rembg.cleanEmployeeInternal start', [
            'filename' => $req->input('filename')
        ]);

        $req->validate(['filename' => 'required|string']);
        $filename = basename($req->input('filename'));
        $src = public_path('uploads/employees/' . $filename);
        if (!is_file($src)) {
            return response()->json(['ok' => false, 'error' => 'source_not_found'], 404);
        }

        try {
            $pipeline = config('photo_pipeline.version', config('photo_bg.pipeline_version', 'v1'));
            $dst = $this->cache->cachedPathFor($src, $pipeline, 'clean');
            $progressKey = $req->input('progress_key');
            $res = $this->bg->clean($src, $dst, $progressKey ?? null);
            if ($res && is_file($dst)) {
                $this->cache->writeManifest($dst, [
                    'pipeline' => 'employee_photo',
                    'version' => $pipeline,
                    'rembg' => config('photo_pipeline.rembg_model'),
                    'created_at' => gmdate('c'),
                ]);
            }
            if ($res === false) {
                return response()->json(['ok' => false, 'error' => 'processing_failed'], 500);
            }
            $rel = str_replace(public_path(), '', $dst);
            $rel = ltrim($rel, '/\\');
            $url = asset($rel);
            Log::info('rembg.cleanEmployeeInternal ok', ['url' => $url, 'rel' => $rel]);
            return response()->json(['ok' => true, 'url' => $url, 'path' => $rel]);
        } catch (\Throwable $e) {
            Log::error('rembg.cleanEmployeeInternal failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'exception'], 500);
        }
    }

    /**
     * POST /rembg/clean-upload
     * Accepts a freshly uploaded image file (e.g., from cropper preview)
     * and returns a cleaned, cached PNG URL.
     */
    public function cleanUpload(Request $req)
    {
        Log::info('rembg.cleanUpload start', [
            'ip' => $req->ip(),
            'content_type' => $req->header('Content-Type'),
        ]);
        try {
            $req->validate(['image' => 'required|file|image|max:5120']);
        } catch (\Throwable $e) {
            Log::warning('rembg.cleanUpload invalid_upload', ['msg' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'invalid_upload'], 422);
        }

            try {
                $file = $req->file('image');
            if (!$file) {
                Log::warning('rembg.cleanUpload no_file');
                return response()->json(['ok' => false, 'error' => 'no_file'], 400);
            }

            $tmpDir = storage_path('app/rembg_tmp');
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0777, true);
                @chmod($tmpDir, 0777);
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $name = 'rembg_' . uniqid('', true) . '.' . $ext;
            $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $name;
            $file->move($tmpDir, $name);

            try {
                $pipeline = config('photo_pipeline.version', config('photo_bg.pipeline_version', 'v1'));
                $dst = $this->cache->cachedPathFor($tmpPath, $pipeline, 'clean');
                $progressKey = $req->input('progress_key');
                $res = $this->bg->clean($tmpPath, $dst, $progressKey ?? null);
                if ($res && is_file($dst)) {
                    $this->cache->writeManifest($dst, [
                        'pipeline' => 'employee_photo',
                        'version' => $pipeline,
                        'rembg' => config('photo_pipeline.rembg_model'),
                        'created_at' => gmdate('c'),
                    ]);
                }
            } finally {
                // cleanup temp file
                if (is_file($tmpPath)) @unlink($tmpPath);
            }

            if ($res === false) {
                Log::error('rembg.cleanUpload processing_failed', ['tmp' => $tmpPath]);
                return response()->json(['ok' => false, 'error' => 'processing_failed'], 500);
            }

            $rel = str_replace(public_path(), '', $dst);
            $rel = ltrim($rel, '/\\');
            $url = asset($rel);
            Log::info('rembg.cleanUpload ok', ['url' => $url, 'rel' => $rel]);
            return response()->json(['ok' => true, 'url' => $url, 'path' => $rel]);
        } catch (\Throwable $e) {
            Log::error('rembg.cleanUpload failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'exception'], 500);
        }
    }

    /**
     * GET /rembg/progress?key=...
     * Returns progress JSON stored in cache by the background removal service.
     */
    public function progress(Request $req)
    {
        $key = (string) $req->query('key', '');
        if (!$key) return response()->json(['ok' => false, 'error' => 'missing_key'], 400);
        $val = Cache::get($key);
        if ($val === null) return response()->json(['ok' => true, 'progress' => ['status' => 'unknown', 'percent' => 0]]);
        return response()->json(['ok' => true, 'progress' => $val]);
    }
}
