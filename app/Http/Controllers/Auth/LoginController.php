<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OpdUnit;
use App\Services\SsoSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    protected SsoSyncService $ssoSync;

    public function __construct(SsoSyncService $ssoSync)
    {
        $this->ssoSync = $ssoSync;
    }
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        // 1) Validasi
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 2) Throttle
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->only('email', 'password');

        // 3) Attempt
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        // 4) Session hygiene
        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user = Auth::user();

        // Ensure OPD/Unit master data is mirrored from SSO if missing
        try {
            $this->ssoSync->ensureOpdMirrorIfMissing();
        } catch (\Throwable $e) {
            // Do not block login on sync failures; just log warning
            logger()->warning('ensureOpdMirrorIfMissing failed during local login: ' . $e->getMessage());
        }

        // 5) Status user
        if (! (bool) ($user->is_active ?? true)) {
            Auth::logout();
            return back()->withErrors(['email' => 'Akun Anda nonaktif. Silakan hubungi admin.']);
        }

        // 6) Validasi Unit OPD (blokir jika nonaktif/soft-deleted)
        if ($user->opd_unit_id) {
            $unit = OpdUnit::withTrashed()->find($user->opd_unit_id);
            if ($unit && ($unit->trashed() || strtoupper($unit->status) === 'NONAKTIF')) {
                Auth::logout();
                return back()->withErrors(['email' => 'Unit OPD Anda nonaktif/dinonaktifkan. Hubungi admin.']);
            }
        }

        // 7) Update last_login_at jika kolom ada
        if (Schema::hasColumn($user->getTable(), 'last_login_at')) {
            $user->forceFill(['last_login_at' => now()])->save();
        }

        // 8) Log login sukses – best effort (jangan ganggu alur)
        try {
            activity('auth')
                ->causedBy($user)
                ->event('login')
                ->withProperties([
                    'ip' => $request->ip(),
                    'ua' => $request->userAgent(),
                ])
                ->log('Login berhasil');
        } catch (\Throwable $e) {
            logger()->warning('Activitylog login failed: '.$e->getMessage());
        }

        // 9) Masuk ke dashboard
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        // Simpan actor dulu sebelum logout
        $actor = Auth::user();

        // Log logout – best effort
        try {
            if ($actor) {
                activity('auth')
                    ->causedBy($actor)
                    ->event('logout')
                    ->withProperties([
                        'ip' => $request->ip(),
                        'ua' => $request->userAgent(),
                    ])
                    ->log('Logout');
            }
        } catch (\Throwable $e) {
            logger()->warning('Activitylog logout failed: '.$e->getMessage());
        }

        // Sesi keluar
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        session()->forget(['impersonate.by', 'impersonate.as']);

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }

    // ===== Throttle helper =====
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
        ]);
    }
}
