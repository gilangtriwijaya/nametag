<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrController extends Controller
{
    /**
     * Generate / refresh QR token (single-active) untuk satu pegawai.
     *
     * - Token baru dibuat sebagai hex 64 char (bin2hex(random_bytes(32))).
     * - Event / hook di model EmployeeQrToken yang mengurus auto-revoke tetap berjalan.
     * - File SVG & PNG token lama dihapus.
     * - File baru disimpan ke public/qrcards/{token}.svg dan {token}.png (PNG best-effort).
     * - Aktivitas dicatat di activity log (revoked + generated).
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $actor   = Auth::user();
        $actorId = $actor?->id;

        return DB::transaction(function () use ($employee, $actor, $actorId) {
            // Ambil token aktif lama (untuk dihapus file-nya setelah token baru dibuat)
            $oldTokens = $employee->qrTokens()
                ->where('status', 'active')
                ->pluck('token')
                ->all();

            // ===== Generate token baru (hex 64) =====
            try {
                $token = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                // Fallback kalau random_bytes tidak tersedia
                $token = bin2hex(openssl_random_pseudo_bytes(32));
            }

            // Simpan token baru ke relasi pegawai
            $new = $employee->qrTokens()->create([
                'token'      => $token,
                'status'     => 'active',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            // ===== Render QR (SVG ber-logo) =====
            $targetUrl = url('/t/' . $token);
            $size      = 512;

            // QR SVG dasar
            $baseSvg = QrCode::format('svg')
                ->size($size)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($targetUrl);

            // Cari logo (SVG/PNG) untuk di-embed
            $logoCandidates = [
                public_path('images/logo-pemda.svg'),
                public_path('images/logo-pemda.png'),
                public_path('anambas-id/images/logo-pemda.svg'),
                public_path('anambas-id/images/logo-pemda.png'),
            ];

            $logoPath = null;
            $logoMime = null;

            foreach ($logoCandidates as $p) {
                if (is_file($p)) {
                    $logoPath = $p;
                    $ext      = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                    $logoMime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                    break;
                }
            }

            $finalSvg = $baseSvg;

            if ($logoPath) {
                try {
                    $logoBinary = file_get_contents($logoPath);
                    if ($logoBinary !== false) {
                        $logoData = 'data:' . $logoMime . ';base64,' . base64_encode($logoBinary);
                        $logoSide = (int) round($size * 0.20); // logo 20% sisi QR
                        $offset   = (int) round(($size - $logoSide) / 2);

                        $logoTag  = '<image x="' . $offset . '" y="' . $offset . '" '
                            . 'width="' . $logoSide . '" height="' . $logoSide . '" '
                            . 'href="' . $logoData . '" />';

                        // Sisipkan sebelum penutup </svg>
                        $finalSvg = preg_replace('/<\/svg>\s*$/', $logoTag . '</svg>', $baseSvg, 1)
                            ?: $baseSvg;
                    }
                } catch (\Throwable $e) {
                    // Gagal embed logo → biarkan QR polos
                    Log::warning('qr.embed_logo_failed', [
                        'token' => $token,
                        'err'   => $e->getMessage(),
                    ]);
                    $finalSvg = $baseSvg;
                }
            }

            // ===== Simpan file SVG & PNG =====
            $publicDir = public_path('qrcards');
            if (!is_dir($publicDir)) {
                @mkdir($publicDir, 0755, true);
            }

            $svgPath = $publicDir . DIRECTORY_SEPARATOR . $token . '.svg';
            file_put_contents($svgPath, $finalSvg);

            // PNG lokal: best-effort (boleh gagal, QR tetap jalan via SVG / route PNG)
            $pngPath = $publicDir . DIRECTORY_SEPARATOR . $token . '.png';

            if (!is_file($pngPath)) {
                try {
                    // Di sini masih pakai layanan eksternal (bisa kamu ganti ke generator lokal nanti)
                    $api  = 'https://api.qrserver.com/v1/create-qr-code/?size=800x800&data=' . urlencode($targetUrl);
                    $blob = @file_get_contents($api);

                    if ($blob) {
                        file_put_contents($pngPath, $blob);
                    }
                } catch (\Throwable $te) {
                    Log::warning('qr.local_png_failed', [
                        'token' => $token,
                        'err'   => $te->getMessage(),
                    ]);
                }
            }

            // ===== Bersihkan file token lama (SVG/PNG) =====
            foreach ($oldTokens as $old) {
                $oldSvg = $publicDir . DIRECTORY_SEPARATOR . $old . '.svg';
                $oldPng = $publicDir . DIRECTORY_SEPARATOR . $old . '.png';

                if (is_file($oldSvg)) {
                    @unlink($oldSvg);
                }
                if (is_file($oldPng)) {
                    @unlink($oldPng);
                }
            }

            // ===== Activity log =====
            try {
                if (!empty($oldTokens)) {
                    activity('qr')
                        ->causedBy($actor)
                        ->performedOn($employee)
                        ->event('revoked')
                        ->withProperties([
                            'employee_id' => $employee->id,
                            'old_tokens'  => $oldTokens,
                        ])
                        ->log('Revoke token QR aktif lama (single-active).');
                }

                activity('qr')
                    ->causedBy($actor)
                    ->performedOn($employee)
                    ->event('generated')
                    ->withProperties([
                        'employee_id' => $employee->id,
                        'token'       => $new->token,
                        'url'         => $targetUrl,
                        'files'       => [
                            'svg' => 'qrcards/' . $token . '.svg',
                            'png' => is_file($pngPath) ? 'qrcards/' . $token . '.png' : null,
                        ],
                    ])
                    ->log('Generate token QR baru.');
            } catch (\Throwable $e) {
                Log::warning('activitylog.qr_failed', [
                    'token' => $token,
                    'err'   => $e->getMessage(),
                ]);
            }

            // ===== Response ke UI =====
            return back()->with([
                'ok'      => 'QR berhasil diperbarui.',
                'qr_url'  => $targetUrl,
                'qr_emp'  => $employee->id,
                'qr_svg'  => asset('qrcards/' . $token . '.svg'),
                'qr_png'  => is_file($pngPath) ? asset('qrcards/' . $token . '.png') : null,
            ]);
        });
    }
}
