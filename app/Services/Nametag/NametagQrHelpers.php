<?php

namespace App\Services\Nametag;

use App\Models\Employee;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

trait NametagQrHelpers
{
    /* =========================
     *  TOKEN & QR URL
     * ========================= */

    private function resolveToken(Employee $e): ?string
    {
        $tok = $e->latest_qr_token ?? $e->qr_token ?? $e->token ?? null;
        if ($tok) {
            return $tok;
        }

        try {
            $tok = \DB::table('employee_qr_tokens')
                ->where('employee_id', $e->id)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->value('token');

            if (!$tok) {
                $tok = \DB::table('employee_qr_tokens')
                    ->where('employee_id', $e->id)
                    ->orderByDesc('id')
                    ->value('token');
            }
        } catch (\Throwable $t) {
            Log::error('nametag: resolveToken DB error', [
                'employee_id' => $e->id,
                'err'         => $t->getMessage(),
            ]);
        }

        return $tok ?: null;
    }

    private function resolveQrUrl(Employee $e): ?string
    {
        $tok = $this->resolveToken($e);
        return $tok ? URL::to('/t/' . $tok) : null;
    }

    /* =========================
     *  QR RENDER (GD-only)
     * ========================= */

    private function drawQrFromDiskOrMake($im, Employee $e, int $x, int $y, int $size): void
    {
        $tok = $this->resolveToken($e);
        Log::info('nametag: front qr call', [
            'employee_id' => $e->id,
            'tok'         => $tok,
            'x'           => $x,
            'y'           => $y,
            'px'          => $size,
        ]);
        if (!$tok) {
            Log::warning('nametag: qr skipped, no token', ['employee_id' => $e->id]);
            return;
        }

        // Kandidat PNG di disk (lokasi resmi)
        $pngCandidates = [
            public_path("qrcards/{$tok}.png"),
        ];

        $src    = null;
        $picked = null;
        foreach ($pngCandidates as $p) {
            if (is_file($p)) {
                $src = @imagecreatefrompng($p);
                if ($src) {
                    $picked = $p;
                    break;
                }
            }
        }

        // Tidak ada PNG → generate via GD lokal
        if (!$src) {
            try {
                config([
                    'image.driver'         => 'gd',
                    'qr-code.driver'       => 'gd',
                    'qr-code.image_driver' => 'gd',
                ]);
                $qrUrl = $this->resolveQrUrl($e);
                if ($qrUrl) {
                    $raw = QrCode::format('png')
                        ->size(max(300, $size * 6))
                        ->margin(0)
                        ->errorCorrection('H')
                        ->generate($qrUrl);
                    $src     = @imagecreatefromstring($raw);
                    $picked  = 'generated';
                    $persist = public_path("qrcards/{$tok}.png");
                    File::ensureDirectoryExists(dirname($persist));
                    if ($src) {
                        imagepng($src, $persist, 6);
                    }
                }
            } catch (\Throwable $t) {
                Log::error('nametag: qr generate failed (gd)', [
                    'employee_id' => $e->id,
                    'err'         => $t->getMessage(),
                ]);
            }
        }

        // Fallback HTTP (terakhir)
        if (!$src) {
            try {
                $qrUrl = $this->resolveQrUrl($e);
                if ($qrUrl) {
                    $api  = 'https://api.qrserver.com/v1/create-qr-code/?size=800x800&margin=0&data=' . urlencode($qrUrl);
                    $blob = @file_get_contents($api);
                    if ($blob) {
                        $src     = @imagecreatefromstring($blob);
                        $picked  = 'downloaded';
                        $persist = public_path("qrcards/{$tok}.png");
                        File::ensureDirectoryExists(dirname($persist));
                        imagepng($src, $persist, 6);
                    }
                }
            } catch (\Throwable $t) {
                Log::error('nametag: qr png fetch failed', [
                    'employee_id' => $e->id,
                    'err'         => $t->getMessage(),
                ]);
            }
        }

        if (!$src) {
            Log::warning('nametag: qr cannot be rasterized', ['employee_id' => $e->id]);
            return;
        }
        Log::info('nametag: qr source used', [
            'employee_id' => $e->id,
            'src'         => $picked,
        ]);

        // Trim tepi putih (convert near-white to alpha)
        $this->whitenToAlpha($src, 245);

        $sw  = imagesx($src);
        $sh  = imagesy($src);
        $tmp = imagecreatetruecolor($size, $size);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $fill = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $fill);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $size, $size, $sw, $sh);

        // Overlay logo Pemda (opsional)
        $logoPath = null;
        foreach ([public_path('images/logo-pemda.png')] as $lp) {
            if (is_file($lp)) {
                $logoPath = $lp;
                break;
            }
        }
        if ($logoPath) {
            $logo = @imagecreatefrompng($logoPath);
            if ($logo) {
                $lw = imagesx($logo);
                $lh = imagesy($logo);

                $target = (int)round($size * 0.20);

                $lg = imagecreatetruecolor($target, $target);
                imagealphablending($lg, true);
                imagesavealpha($lg, false);
                $white = imagecolorallocate($lg, 255, 255, 255);
                imagefilledrectangle($lg, 0, 0, $target, $target, $white);
                imagecopyresampled($lg, $logo, 0, 0, 0, 0, $target, $target, $lw, $lh);

                $ox = (int)round(($size - $target) / 2);
                $oy = (int)round(($size - $target) / 2);
                imagecopy($tmp, $lg, $ox, $oy, 0, 0, $target, $target);

                imagedestroy($lg);
                imagedestroy($logo);
            }
        }

        imagecopy($im, $tmp, $x, $y, 0, 0, $size, $size);

        imagedestroy($tmp);
        imagedestroy($src);
    }

    /* =========================
     *  UTIL IMAGE
     * ========================= */

    private function whitenToAlpha($im, int $threshold = 245): void
    {
        // Convert near-white background to alpha while preserving original RGB colors.
        // This avoids changing the signature color (e.g., blue) while making white background transparent.
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $w = imagesx($im);
        $h = imagesy($im);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $col = imagecolorat($im, $x, $y);
                $r   = ($col >> 16) & 0xFF;
                $g   = ($col >> 8) & 0xFF;
                $b   = $col & 0xFF;

                // compute luma (perceived brightness)
                $luma = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

                if ($luma >= $threshold) {
                    // map luma in [threshold,255] -> alpha in [0..127]
                    $alphaFrac = ($luma - $threshold) / max(1, (255 - $threshold));
                    $alpha = (int) round(min(1.0, $alphaFrac) * 127);
                    // fully transparent for pure white
                    if ($r === 255 && $g === 255 && $b === 255) $alpha = 127;
                    imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $g, $b, $alpha));
                }
                // otherwise keep pixel as-is (opaque)
            }
        }
    }

    /**
     * Remove background similar to sampled corner color and convert it to alpha.
     * This preserves original RGB colors (including blue ink) while removing
     * photographed paper backgrounds that are not pure white.
     *
     * @param resource $im
     * @param int $threshold color distance threshold (0-255)
     */
    private function removeBgToAlpha($im, int $threshold = 60): void
    {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $w = imagesx($im);
        $h = imagesy($im);

        // sample small areas in four corners to estimate background color
        $sample = function($sx, $sy, $sw, $sh) use ($im) {
            $sum = [0,0,0]; $count = 0;
            for ($y=$sy; $y<$sy+$sh && $y<imagesy($im); $y++) {
                for ($x=$sx; $x<$sx+$sw && $x<imagesx($im); $x++) {
                    $c = imagecolorat($im,$x,$y);
                    $r = ($c>>16)&0xFF; $g = ($c>>8)&0xFF; $b = $c&0xFF;
                    $sum[0]+=$r; $sum[1]+=$g; $sum[2]+=$b; $count++;
                }
            }
            if ($count===0) return [255,255,255];
            return [ (int)round($sum[0]/$count), (int)round($sum[1]/$count), (int)round($sum[2]/$count) ];
        };

        $sw = max(1, (int)round($w * 0.08));
        $sh = max(1, (int)round($h * 0.08));
        $c1 = $sample(0,0,$sw,$sh);
        $c2 = $sample(max(0,$w-$sw),0,$sw,$sh);
        $c3 = $sample(0,max(0,$h-$sh),$sw,$sh);
        $c4 = $sample(max(0,$w-$sw),max(0,$h-$sh),$sw,$sh);

        $bg = [ (int)round(($c1[0]+$c2[0]+$c3[0]+$c4[0])/4), (int)round(($c1[1]+$c2[1]+$c3[1]+$c4[1])/4), (int)round(($c1[2]+$c2[2]+$c3[2]+$c4[2])/4) ];

        // For each pixel, compute color distance to bg; convert close colors to transparent
        for ($y=0;$y<$h;$y++) {
            for ($x=0;$x<$w;$x++) {
                $col = imagecolorat($im,$x,$y);
                $r = ($col>>16)&0xFF; $g = ($col>>8)&0xFF; $b = $col&0xFF;
                $dr = $r - $bg[0]; $dg = $g - $bg[1]; $db = $b - $bg[2];
                $dist = sqrt($dr*$dr + $dg*$dg + $db*$db);
                if ($dist <= $threshold) {
                    // fully transparent
                    $alpha = 127;
                } elseif ($dist <= $threshold*2) {
                    // partially transparent (smooth edge)
                    $alpha = (int)round(127 * ( ($dist - $threshold) / $threshold ));
                    if ($alpha>127) $alpha=127;
                } else {
                    $alpha = 0;
                }
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $g, $b, $alpha));
            }
        }
    }

    private function trimWhite($im, int $threshold = 245)
    {
        $w    = imagesx($im);
        $h    = imagesy($im);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                if (!($r >= $threshold && $g >= $threshold && $b >= $threshold)) {
                    if ($x < $minX) $minX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($x > $maxX) $maxX = $x;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($maxX < 0) {
            return $im; // putih semua
        }

        $rect    = ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1];
        $cropped = imagecrop($im, $rect);
        return $cropped ?: $im;
    }
}
