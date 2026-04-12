<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ValidateSsoSession
{
    /**
     * Validate SSO session is still valid and user exists
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // User authenticated but user record doesn't exist = session stale
        if ($user && !$user->exists) {
            Log::warning('[ValidateSsoSession] User record does not exist', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);
            
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route('sso.login')
                ->with('error', 'Data sesi tidak ditemukan. Silakan login kembali.');
        }

        // Verify user is still active
        if ($user && $user->is_active !== 1) {
            Log::warning('[ValidateSsoSession] User inactive', [
                'user_id' => $user->id,
                'is_active' => $user->is_active,
            ]);
            
            Auth::logout();
            $request->session()->invalidate();
            
            return redirect()->route('sso.login')
                ->with('error', 'Akun Anda tidak aktif. Hubungi administrator.');
        }

        // Verify session payload integrity
        $ssoUser = session('sso.user');
        if ($user && $ssoUser && (int)($user->sso_user_id ?? 0) !== (int)($ssoUser['id'] ?? 0)) {
            Log::warning('[ValidateSsoSession] SSO User ID mismatch', [
                'db_sso_user_id' => $user->sso_user_id,
                'session_sso_user_id' => $ssoUser['id'] ?? null,
            ]);
            
            Auth::logout();
            $request->session()->invalidate();
            
            return redirect()->route('sso.login')
                ->with('error', 'Sesi SSO tidak valid. Silakan login kembali.');
        }

        return $next($request);
    }
}
