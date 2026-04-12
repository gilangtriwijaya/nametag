<?php

namespace App\Services\Nametag;

use App\Models\Employee;
use App\Models\Opd;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

trait NametagPathHelpers
{
    /** Direktori output: public/nametag/{front|back} */
    private function outputDir(string $side): string
    {
        return public_path("nametag/{$side}");
    }

    /** Pastikan folder front/back ada */
    private function ensureDirs(): void
    {
        foreach (['front', 'back'] as $side) {
            $dir = $this->outputDir($side);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
                Log::info('nametag: created dir', ['dir' => $dir]);
            }
        }
    }

    /** mm → px dari lebar template & size_mm.w */
    private function ppm($im, array $tplCfg): float
    {
        $tplWpx = imagesx($im);
        $tplWmm = max(1e-6, (float)($tplCfg['size_mm']['w'] ?? 141.12));
        return $tplWpx / $tplWmm;
    }

    /** Deteksi path absolut (unix / windows) */
    private function isAbsolutePath(string $path): bool
    {
        // Unix: /foo/bar; Windows: C:\foo\bar
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * Normalisasi path template dari config ke
     *  - public_path('templates/...') atau
     *  - path absolut jika sudah lengkap
     */
    private function resolveTemplatePath(?string $tpl, string $side): ?string
    {
        if (!$tpl) {
            return null;
        }

        $original   = $tpl;
        $candidates = [];

        // Kalau absolut dan ada → langsung pakai
        if ($this->isAbsolutePath($tpl) && @is_file($tpl)) {
            return $tpl;
        }

        // Relatif ke public_path
        $rel = ltrim($tpl, '/');

        // Kalau user sudah menulis "templates/PolosFront.png"
        $candidates[] = public_path($rel);

        // Kalau user hanya tulis "PolosFront.png", paksa ke "templates/PolosFront.png"
        if (strpos($rel, 'templates/') !== 0) {
            $candidates[] = public_path('templates/' . $rel);
        }

        foreach ($candidates as $path) {
            if ($path && @is_file($path)) {
                Log::info('nametag: template resolved', [
                    'side'       => $side,
                    'config'     => $original,
                    'resolved'   => $path,
                    'candidates' => $candidates,
                ]);
                return $path;
            }
        }

        Log::error('nametag: template not found', [
            'side'       => $side,
            'config'     => $original,
            'candidates' => $candidates,
        ]);

        return null;
    }

    /* =========================
     *  FONT & ASSETS
     * ========================= */

    /**
     * Resolve a TTF path for the renderer.
     *
     * Resolution order:
     * 1. If a named font key is provided and exists in `nametag.fonts`, prefer that.
     * 2. Backwards-compatible `nametag.font` single entry.
     * 3. Fallback to `nametag.fonts.primary` if present.
     * 4. Hardcoded system font candidates.
     */
    private function resolveFont(bool $bold = false, ?string $fontKey = null): ?string
    {
        $candidates = [];

        // 1) named fonts (preferred)
        if ($fontKey) {
            $base = config("nametag.fonts.{$fontKey}", null);
            if ($base && is_array($base)) {
                $candidates[] = $bold ? ($base['bold'] ?? null) : ($base['regular'] ?? null);
            }
        }

        // 2) backwards-compatible single-font config
        $single = config('nametag.font', null);
        if (is_array($single)) {
            $candidates[] = $bold ? ($single['bold'] ?? null) : ($single['regular'] ?? null);
        }

        // 3) primary named font
        $primary = config('nametag.fonts.primary', null);
        if (is_array($primary)) {
            $candidates[] = $bold ? ($primary['bold'] ?? null) : ($primary['regular'] ?? null);
        }

        // 4) system / default candidates
        $candidates[] = public_path('fonts/Inter-' . ($bold ? 'Bold' : 'Regular') . '.ttf');
        $candidates[] = '/usr/share/fonts/truetype/dejavu/' . ($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf');

        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $p) {
            if ($p && @is_file($p)) {
                return $p;
            }
        }

        Log::warning('nametag: font not found', ['bold' => $bold, 'fontKey' => $fontKey, 'candidates' => $candidates]);
        return null;
    }

    /**
     * Foto:
     *  1) $employee->foto_path (relatif ke public)
     *  2) fallback uploads/employees/{clean|nametag}/{id}.{png/jpg}
     */
    private function resolvePhoto(Employee $e): ?string
    {
        // Clear PHP's internal stat cache to ensure we get fresh file metadata.
        // This fixes cases where a photo file has been directly replaced on disk
        // (e.g., via SCP or direct file upload) but PHP still "remembers" the old file.
        clearstatcache(true);

        $cands = [];

        // 1) Sumber eksplisit dari DB (prioritas)
        if ($e->foto_path) {
            $abs = public_path(ltrim($e->foto_path, '/'));
            $dbPathExists = @is_file($abs);
            if ($dbPathExists) {
                $cands[] = $abs;
            }
            Log::debug('nametag: resolve foto step1 DB', [
                'employee_id' => $e->id,
                'db_foto_path' => $e->foto_path,
                'absolute_path' => $abs,
                'file_exists' => $dbPathExists,
                'file_size' => $dbPathExists ? @filesize($abs) : 0,
                'file_mtime' => $dbPathExists ? @filemtime($abs) : 0,
            ]);
        }

        // 2) Versi clean / nametag (standar path)
        $fallbacks = [
            public_path("uploads/employees/clean/{$e->id}.png"),
            public_path("uploads/employees/clean/{$e->id}.jpg"),
            public_path("uploads/employees/nametag/{$e->id}.png"),
            public_path("uploads/employees/nametag/{$e->id}.jpg"),
        ];
        
        foreach ($fallbacks as $p) {
            if (@is_file($p)) {
                $cands[] = $p;
                Log::debug('nametag: resolve foto fallback found', [
                    'employee_id' => $e->id,
                    'path' => $p,
                    'file_size' => @filesize($p),
                    'file_mtime' => @filemtime($p),
                ]);
            }
        }

        if (!$cands) {
            Log::warning('nametag: photo not found', [
                'employee_id' => $e->id,
                'foto_path'   => $e->foto_path,
                'fallback_checks' => $fallbacks,
            ]);
            return null;
        }

        // pilih termutakhir - compare BOTH mtime dan ctime (use whichever is newer)
        // This handles cases where file is copied with preserved timestamps (mtime lama, ctime baru)
        usort($cands, function($a, $b) {
            // Use the max of mtime and ctime for each file
            $atime = max(@filemtime($a), @filectime($a));
            $btime = max(@filemtime($b), @filectime($b));
            return $btime <=> $atime;
        });
        $pick = $cands[0];

        Log::info('nametag: photo picked', [
            'employee_id' => $e->id,
            'pick'        => $pick,
            'mtime'       => @date('c', @filemtime($pick)),
            'ctime'       => @date('c', @filectime($pick)),
            'actual_time' => @date('c', max(@filemtime($pick), @filectime($pick))),
            'size'        => @filesize($pick),
            'num_candidates' => count($cands),
            'candidates'  => $cands,
        ]);

        return $pick;
    }

    private function resolveSignaturePath(): ?string
    {
        // Prefer explicit files named ttd_setda.* placed in public/uploads/opd
        $candidates = [
            public_path('uploads/opd/ttd_setda.png'),
            public_path('uploads/opd/ttd_setda.jpg'),
            public_path('uploads/opd/ttd_setda.jpeg'),
            public_path('uploads/opd/ttd_setda.webp'),
            public_path('uploads/opd/ttd_setda.bmp'),
        ];

        foreach ($candidates as $p) {
            if ($p && @is_file($p)) return $p;
        }

        // Fallback: scan uploads/opd for files that look like signature (contain 'ttd' or 'ttd_setda')
        $dir = public_path('uploads/opd');
        if (@is_dir($dir)) {
            $files = glob($dir . '/*.{png,jpg,jpeg,webp,bmp}', GLOB_BRACE) ?: [];
            usort($files, function ($a, $b) {
                $wa = (stripos(basename($a), 'ttd_setda') !== false || stripos(basename($a), 'ttd') !== false) ? 0 : 1;
                $wb = (stripos(basename($b), 'ttd_setda') !== false || stripos(basename($b), 'ttd') !== false) ? 0 : 1;
                if ($wa === $wb) return filemtime($b) <=> filemtime($a);
                return $wa <=> $wb;
            });
            if (!empty($files)) {
                return $files[0];
            }
        }

        Log::warning('nametag: signature not found', []);
        return null;
    }

    /**
     * Cari stempel (cap) di folder uploads/opd atau file dengan nama yang lazim.
     */
    private function resolveStampPath(): ?string
    {
        // cek file spesifik terlebih dahulu
        $candidates = [
            public_path('uploads/opd/stempel_setda.png'),
            public_path('uploads/opd/stempel_setda.jpg'),
            public_path('uploads/opd/stempel_setda.jpeg'),
            public_path('uploads/opd/stempel.png'),
            public_path('uploads/opd/stempel.jpg'),
        ];

        foreach ($candidates as $p) {
            if ($p && @is_file($p)) return $p;
        }

        // fallback: scan folder public/uploads/opd untuk file yang mengandung "stempel" atau "cap"
        $dir = public_path('uploads/opd');
        if (@is_dir($dir)) {
            $files = glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
            // prioritas nama yang mengandung stempel/cap
            usort($files, function ($a, $b) {
                $wa = (stripos(basename($a), 'stempel') !== false || stripos(basename($a), 'cap') !== false) ? 0 : 1;
                $wb = (stripos(basename($b), 'stempel') !== false || stripos(basename($b), 'cap') !== false) ? 0 : 1;
                if ($wa === $wb) return filemtime($b) <=> filemtime($a);
                return $wa <=> $wb;
            });
            if (!empty($files)) {
                return $files[0];
            }
        }

        return null;
    }

    private function loadImage(string $path)
    {
        if (!is_file($path) || filesize($path) === 0) {
            return null;
        }

        // Auto-detect actual file format from file header/signature
        // instead of relying on extension (which can be wrong)
        $header = @file_get_contents($path, false, null, 0, 12);
        if (!$header) {
            return null;
        }

        // Detect file format by magic bytes/signature
        $hex = bin2hex(substr($header, 0, 4));

        // JPEG: ffd8ffe0 or ffd8ffe1
        if (substr($hex, 0, 4) === 'ffd8') {
            $img = @imagecreatefromjpeg($path);
            if ($img) return $img;
        }

        // PNG: 89504e47
        if (substr($header, 0, 4) === "\x89PNG") {
            $img = @imagecreatefrompng($path);
            if ($img) return $img;
        }

        // WebP: 52494646...5750
        if (substr($hex, 0, 8) === '52494646' && strpos($hex, '57453050') !== false) {
            if (function_exists('imagecreatefromwebp')) {
                $img = @imagecreatefromwebp($path);
                if ($img) return $img;
            }
        }

        // Fallback: try by extension if signature detection failed
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png'         => @imagecreatefrompng($path),
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default       => null,
        };
    }
}
