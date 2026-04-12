<?php

namespace App\Services;

use App\Models\Employee;
use App\Services\PhotoBgService;
use App\Support\EmployeeBg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeePhotoService
{
    public function __construct(
        protected PhotoBgService $bgService
    ) {}

    /* =====================================================
     | PUBLIC API
     ===================================================== */

    public function uploadAndProcess(
        ?UploadedFile $file,
        Employee $employee,
        array $crop = [],
        ?string $cleanedPath = null
    ): bool {
        if (! $file && ! $cleanedPath) {
            return false;
        }

        return $this->processPipeline(
            employee: $employee,
            uploadedFile: $file,
            cleanedPath: $cleanedPath,
            crop: $crop
        );
    }

    public function syncBackgroundByJabatan(Employee $employee): bool
    {
        if (! $employee->foto_path) {
            return false;
        }

        $src = public_path($employee->foto_path);
        if (! is_file($src)) {
            return false;
        }

        $cleanCache = public_path("uploads/employees/clean/{$employee->id}.png");

        return $this->processPipeline(
            employee: $employee,
            uploadedFile: null,
            cleanedPath: is_file($cleanCache) ? $cleanCache : $src,
            crop: []
        );
    }

    public function deleteAll(Employee $employee): void
    {
        foreach ([
            $employee->foto_path ? public_path($employee->foto_path) : null,
            public_path("uploads/employees/clean/{$employee->id}.png"),
            public_path("uploads/employees/nametag/{$employee->id}.png"),
        ] as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Ensure the employee photo is processed according to business flow:
     * 1) original -> rembg clean (transparent PNG)
     * 2) compose transparent PNG onto jabatan color
     * 3) persist final foto and cache transparent PNG in uploads/employees/clean/{id}.png
     */
    public function ensureProcessed(Employee $employee): bool
    {
        if (! $employee->foto_path) return false;

        $src = public_path(ltrim($employee->foto_path, '/'));
        if (!is_file($src)) {
            // try to pick any existing candidate (nametag/clean by id)
            $candidates = [
                public_path("uploads/employees/{$employee->id}.png"),
                public_path("uploads/employees/nametag/{$employee->id}.png"),
                public_path("uploads/employees/clean/{$employee->id}.png"),
            ];
            foreach ($candidates as $c) if (is_file($c)) { $src = $c; break; }
        }

        if (! is_file($src)) return false;

        $workDir  = public_path('uploads/employees/_work');
        $finalDir = public_path('uploads/employees');
        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($finalDir);

        // 1) run rembg -> get transparent PNG
        $tmpTrans = $workDir . '/_rembg_' . uniqid() . '.png';
        $removal = app(BackgroundRemovalService::class);
        $cache = app(ImageCacheService::class);
        $pipeline = config('photo_pipeline.version', config('photo_bg.pipeline_version', 'v1'));

        $res = $removal->clean($src, $tmpTrans);
        if ($res === false || ! is_file($tmpTrans)) {
            Log::warning('ensureProcessed: rembg failed', ['employee_id' => $employee->id, 'src' => $src]);
            // apply failure policy: continue using original as final if configured
            $policy = config('photo_pipeline.failure_policy', 'use_original');
            if ($policy === 'fail') return false;
            if ($policy === 'use_previous') {
                return false; // caller can decide; for now fail
            }
            // fallback: use original source as transparent proxy (no alpha)
            $tmpTrans = $src;
        }

        // also persist a cache copy (stable name) in derived cache under job key
        $type    = \App\Support\EmployeeBg::typeFromEmployee($employee);
        $style   = \App\Services\JobPhotoStyle::forJob($type);
        $bgColor = $style->bgColor();

        $cachedPath = $cache->cachedPathFor($src, $pipeline, $type);
        @mkdir(dirname($cachedPath), 0777, true);
        if (is_file($tmpTrans) && $tmpTrans !== $src) {
            @copy($tmpTrans, $cachedPath);
        } else {
            // if rembg failed and we are using original, create a placeholder manifest
            $cache->writeManifest($cachedPath, [
                'pipeline' => 'employee_photo',
                'version' => $pipeline,
                'bg' => $bgColor,
                'rembg' => config('photo_pipeline.rembg_model'),
                'halo' => config('photo_pipeline.halo', []),
                'rembg_failed' => true,
                'created_at' => gmdate('c'),
            ]);
        }

        // 2) compose onto jabatan color — final lives in derived/final namespaced folder
        $uuid = (string) Str::uuid();
        $finalDirDerived = public_path('uploads/derived/employees/final');
        @mkdir($finalDirDerived, 0777, true);
        $final = "{$finalDirDerived}/{$uuid}.png";

        $ok = $this->bgService->composeToColor($tmpTrans, $final, $bgColor);
        if (! $ok || ! is_file($final)) {
            // fallback to full pipeline
            try {
                $ok = $this->bgService->processAndCompose($src, $tmpTrans, $final, $bgColor);
            } catch (\Throwable $e) {
                Log::error('ensureProcessed: pipeline failed', ['err' => $e->getMessage(), 'employee_id' => $employee->id]);
                return false;
            }
        }

        if (! is_file($final)) return false;

        // 3) persist: remove previous file, save final and cache cleaned
        if ($employee->foto_path) {
            @unlink(public_path($employee->foto_path));
        }
        // Save derived final path (public relative)
        $employee->foto_path = 'uploads/derived/employees/final/' . basename($final);
        $employee->updated_by = Auth::id();
        $employee->save();

        // copy cleaned transparent to derived cache by id (legacy compatibility)
        if (is_file($tmpTrans) && $tmpTrans !== $src) {
            $cleanDir = public_path('uploads/derived/employees/clean');
            File::ensureDirectoryExists($cleanDir);
            @copy($tmpTrans, "{$cleanDir}/{$employee->id}.png");
        }

        // write final manifest for audit/debugging
        $meta = [
            'pipeline' => 'employee_photo',
            'version' => $pipeline,
            'bg' => $bgColor,
            'rembg' => config('photo_pipeline.rembg_model'),
            'halo' => config('photo_pipeline.halo', []),
            'job' => $type,
            'style' => $style->style(),
            'created_at' => gmdate('c'),
        ];
        try {
            $cache->writeManifest($final, $meta);
        } catch (\Throwable $e) {
            // ignore manifest write failures
        }

        // cleanup work files
        $this->cleanup([$tmpTrans]);

        Log::info('photo.pipeline.done', [
            'employee_id' => $employee->id,
            'job' => $type,
            'cache' => is_file($cachedPath) ? 'miss' : 'miss',
            'ms' => 0,
        ]);

        return true;
    }

    /* =====================================================
     | CORE PIPELINE
     ===================================================== */

    private function processPipeline(
        Employee $employee,
        ?UploadedFile $uploadedFile,
        ?string $cleanedPath,
        array $crop
    ): bool {
        $workDir  = public_path('uploads/employees/_work');
        $finalDir = public_path('uploads/employees');

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($finalDir);

        $uuid = (string) Str::uuid();

        $tmpSrc   = "{$workDir}/src_{$uuid}";
        $tmpTrans = "{$workDir}/trans_{$uuid}.png";
        $final    = "{$finalDir}/{$uuid}.png";

        $cleanup = [];
            $usedCleanedSource = false;

        try {
            /* ===============================
             * 1. SOURCE
             * Prefer a provided cleaned path (from client) over the raw uploaded file.
             * This ensures that when the UI auto-cleans and sets `foto_path`, the
             * server uses that cleaned transparent PNG instead of re-processing the
             * original uploaded file which may still contain background.
             * =============================== */
            if ($cleanedPath) {
                $abs = $this->resolvePublicPath($cleanedPath);
                if ($abs) {
                    $tmpSrc .= '.png';
                    if (! @copy($abs, $tmpSrc)) {
                        throw new \RuntimeException('Gagal copy clean source');
                    }
                } elseif ($uploadedFile) {
                    // fallback to uploaded file if cleaned path is invalid
                    $uploadedFile = $uploadedFile; // noop — keep branch below
                }
            }

            if ($uploadedFile && !isset($abs)) {
                $ext = strtolower($uploadedFile->getClientOriginalExtension() ?: 'jpg');
                if (! in_array($ext, ['jpg','jpeg','png'])) {
                    throw new \RuntimeException('Format foto tidak didukung');
                }

                $tmpSrc .= ".{$ext}";
                $uploadedFile->move($workDir, basename($tmpSrc));

                if (! is_file($tmpSrc)) {
                    throw new \RuntimeException('Upload gagal disimpan');
                }
            } else {
                if (!isset($abs)) {
                    $abs = $this->resolvePublicPath($cleanedPath);
                }
                if (! $abs) {
                    throw new \RuntimeException('Clean source tidak valid');
                }

                $tmpSrc .= '.png';
                if (! @copy($abs, $tmpSrc)) {
                    throw new \RuntimeException('Gagal copy clean source');
                }
                    $usedCleanedSource = true;
            }

            $cleanup[] = $tmpSrc;

            /* ===============================
             * 2. BACKGROUND
             * =============================== */
            $type    = EmployeeBg::typeFromEmployee($employee);
            $bgColor = EmployeeBg::bgHexForType($type);

            $ok = $this->bgService->processAndCompose(
                $tmpSrc,
                $tmpTrans,
                $final,
                $bgColor
            );

            if (! $ok || ! is_file($final)) {
                throw new \RuntimeException('Gagal generate foto final');
            }

            $cleanup[] = $tmpTrans;

            /* ===============================
             * 3. CROP
             * =============================== */
                // If we used the cleaned image produced by the client (which is
                // already cropped to the preview), do NOT re-apply the crop on the
                // server -- that would double-crop and cause the final selection
                // to differ from what the user previewed.
                if ($this->hasValidCrop($crop) && ! $usedCleanedSource) {
                    $this->cropToSlotSafe($final, $crop);
            }

            /* ===============================
             * 4. COMMIT
             * =============================== */
            if ($employee->foto_path) {
                @unlink(public_path($employee->foto_path));
            }

            $employee->foto_path  = "uploads/employees/" . basename($final);
            $employee->updated_by = Auth::id();
            $employee->save();

            /* ===============================
             * 5. CACHE CLEAN
             * =============================== */
            if (is_file($tmpTrans)) {
                $cleanDir = public_path('uploads/employees/clean');
                File::ensureDirectoryExists($cleanDir);
                @copy($tmpTrans, "{$cleanDir}/{$employee->id}.png");
            }

            Log::info('Employee photo processed', [
                'employee_id' => $employee->id,
                'path'        => $employee->foto_path,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('Employee photo pipeline error', [
                'employee_id' => $employee->id,
                'error'       => $e->getMessage(),
            ]);

            if (is_file($final)) {
                @unlink($final);
            }

            return false;

        } finally {
            $this->cleanup($cleanup);
        }
    }

    /* =====================================================
     | INTERNAL HELPERS
     ===================================================== */

    private function resolvePublicPath(?string $path): ?string
    {
        if (! $path) return null;

        $p = trim($path);
        // If caller already passed an absolute filesystem path, accept it.
        if (is_file($p)) {
            return $p;
        }

        $normalized = ltrim($p, '/');
        if (str_contains($normalized, '..')) return null;

        $abs = public_path($normalized);
        return is_file($abs) ? $abs : null;
    }

    private function hasValidCrop(array $crop): bool
    {
        return isset($crop['x'],$crop['y'],$crop['w'],$crop['h'])
            && $crop['w'] >= 10
            && $crop['h'] >= 10;
    }

    private function cropToSlotSafe(string $path, array $crop): void
    {
        $src = @imagecreatefrompng($path);
        if (! $src) return;

        $sw = imagesx($src);
        $sh = imagesy($src);

        $x = max(0, min($sw - 1, (int)$crop['x']));
        $y = max(0, min($sh - 1, (int)$crop['y']));
        $w = max(1, min($sw - $x, (int)$crop['w']));
        $h = max(1, min($sh - $y, (int)$crop['h']));

        $tw = (int) config('photo_bg.slot_width_px', 560);
        $th = (int) config('photo_bg.slot_height_px', 706);

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);

        imagecopyresampled(
            $dst,
            $src,
            0, 0,
            $x, $y,
            $tw, $th,
            $w, $h
        );

        imagepng($dst, $path, 6);
        imagedestroy($src);
        imagedestroy($dst);
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            if ($p && is_file($p)) {
                @unlink($p);
            }
        }
    }
}
