<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\OpdUnit;

/**
 * Menjamin hanya user aktif (dan unitnya aktif) yang boleh mengakses aplikasi.
 *
 * Logika:
 * - Jika belum login: teruskan (biarkan middleware auth yang menangani)
 * - Jika impersonasi: bypass (opsional, mudah dimatikan)
 * - Jika users.is_active = 0: tendang
 * - Jika user punya opd_unit_id dan opd_units.status != 'AKTIF': tendang
 *
 * Catatan:
 * - Kolom users.is_active opsional; jika tidak ada/NULL -> dianggap true.
 * - Jika ingin NULL dianggap nonaktif, ganti default-nya.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        // Belum login? biar 'auth' yang kerja.
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // OPSIONAL: izinkan superadmin yang sedang impersonasi
        // untuk mengakses akun nonaktif demi perbaikan.
        if (session()->has('impersonate.by')) {
            return $next($request);
        }

        // 1) Cek status aktif user itu sendiri
        // Default: NULL => dianggap aktif (true).
        $userActive = (bool) ($user->is_active ?? true);
        if ($userActive === false) {
            return $this->deny($request, 'Akun dinonaktifkan. Hubungi admin.');
        }

        // 2) Cek status unit (jika user terikat unit)
        // Asumsikan kolom: users.opd_unit_id -> opd_units.id dan
        // kolom status di opd_units adalah 'AKTIF' / 'NONAKTIF'.
        if (!empty($user->opd_unit_id)) {
            $unit = OpdUnit::select('status')->find($user->opd_unit_id);

            // Kalau record tidak ketemu, anggap nonaktif untuk aman.
            $unitActive = $unit ? ($unit->status === 'AKTIF') : false;

            if ($unitActive === false) {
                return $this->deny(
                    $request,
                    'Unit Anda sedang NONAKTIF. Hubungi admin OPD.'
                );
            }
        }

        return $next($request);
    }

    /**
     * Tanggapi penolakan akses:
     * - Jika request JSON/AJAX: 403 JSON
     * - Jika web: paksa logout + redirect ke login dengan error
     */
    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $message], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }
}
