<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrPublicController extends Controller
{
    /**
     * Ambil token aktif/terakhir berdasarkan employee_id.
     * Tidak dipakai rute publik, tapi tetap dipertahankan untuk ekstensi internal.
     */
    private function resolveTokenByEmployeeId(?int $employeeId): ?string
    {
        if (!$employeeId) {
            return null;
        }

        $tok = DB::table('employee_qr_tokens')
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('token');

        if (!$tok) {
            $tok = DB::table('employee_qr_tokens')
                ->where('employee_id', $employeeId)
                ->orderByDesc('id')
                ->value('token');
        }

        return $tok ?: null;
    }

    /**
     * Render PNG QR Code publik berdasarkan token.
     *
     * URL: /qr/png/{token}
     *
     * Token sudah disaring dari route regex: [A-Fa-f0-9]{64}
     */
    public function pngByToken(Request $request, string $token)
    {
        try {
            // Ukuran QR dibatasi untuk keamanan server
            $size = max(128, min(2048, (int) $request->integer('size', 512)));

            // Generate QR dasar (PNG) sebagai binary string
            $qrRaw = QrCode::format('png')
                ->size($size)
                ->margin(2)
                ->errorCorrection('H')
                ->generate(url('/t/' . $token));

            // Create GD image dari qrRaw
            $qr = @imagecreatefromstring($qrRaw);
            if (!$qr) {
                abort(500, 'QR raster failed (invalid image data).');
            }

            $w = imagesx($qr);
            $h = imagesy($qr);

            /* ===================================================================
               LOGO OVERLAY (PNG, transparan)
               =================================================================== */
            $logoPaths = [
                public_path('anambas-id/images/logo-pemda.png'),
                public_path('images/logo-pemda.png'),
            ];

            $logo = null;
            foreach ($logoPaths as $p) {
                if (is_file($p)) {
                    $logo = @imagecreatefrompng($p);
                    if ($logo) break;
                }
            }

            if ($logo) {
                imagealphablending($qr, true);
                imagesavealpha($qr, true);

                $lw = imagesx($logo);
                $lh = imagesy($logo);

                // Logo = 20% dari sisi QR
                $targetSize = (int) round(min($w, $h) * 0.20);

                // Canvas sementara untuk resize logo
                $tmp = imagecreatetruecolor($targetSize, $targetSize);
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);

                $alpha = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                imagefill($tmp, 0, 0, $alpha);

                // Scale logo
                imagecopyresampled($tmp, $logo, 0, 0, 0, 0, $targetSize, $targetSize, $lw, $lh);

                // Posisi tengah QR
                $ox = (int) round(($w - $targetSize) / 2);
                $oy = (int) round(($h - $targetSize) / 2);

                imagecopy($qr, $tmp, $ox, $oy, 0, 0, $targetSize, $targetSize);

                imagedestroy($tmp);
                imagedestroy($logo);
            }

            /* ===================================================================
               OUTPUT PNG
               =================================================================== */
            ob_start();
            imagepng($qr, null, 6);
            imagedestroy($qr);
            $png = ob_get_clean();

            return response($png, 200, [
                'Content-Type'  => 'image/png',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]);
        } catch (\Throwable $e) {
            Log::error('qr.png.public failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            abort(500, 'QR PNG error.');
        }
    }
}
