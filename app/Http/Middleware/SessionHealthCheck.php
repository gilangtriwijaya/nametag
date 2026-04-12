<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SessionHealthCheck
{
    /**
     * Check session health without being overly strict
     * If session seems broken, regenerate instead of logout
     */
    public function handle(Request $request, Closure $next)
    {
        // Only check if user authenticated
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $sessionId = session()->getId();

        // Check 1: Session ID is not empty
        if (empty($sessionId)) {
            Log::warning('[SessionHealthCheck] Empty session ID, regenerating', ['user_id' => $user->id ?? null]);
            session()->regenerate();
        }

        // Check 2: User record still exists in real database query (not cached)
        try {
            $exists = \DB::connection(config('database.default'))
                ->table('users')
                ->where('id', $user->id)
                ->exists();
            
            if (!$exists) {
                Log::warning('[SessionHealthCheck] User record not found', ['user_id' => $user->id]);
                auth()->logout();
                return redirect()->route('sso.login')
                    ->with('error', 'Data pengguna tidak ditemukan. Silakan login kembali.');
            }
        } catch (\Throwable $e) {
            // Database error → log but don't block, let normal flow handle it
            Log::error('[SessionHealthCheck] Database check failed: ' . $e->getMessage());
        }

        // Check 3: Session payload consistency
        $ssoUser = session('sso.user');
        if ($ssoUser && isset($ssoUser['id'])) {
            if ((int)($user->sso_user_id ?? 0) !== (int)($ssoUser['id'] ?? 0)) {
                Log::warning('[SessionHealthCheck] SSO ID mismatch - regenerating', [
                    'db_sso_user_id' => $user->sso_user_id,
                    'session_sso_user_id' => $ssoUser['id'],
                ]);
                session()->regenerate();
            }
        }

        return $next($request);
    }
}
