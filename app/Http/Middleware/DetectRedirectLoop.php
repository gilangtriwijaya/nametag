<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class DetectRedirectLoop
{
    /**
     * Detect and prevent redirect loops by tracking request chain
     * Redirect loop = same URL requested multiple times in short time
     */
    public function handle(Request $request, Closure $next)
    {
        $userId = Auth::id();
        if (!$userId) {
            return $next($request);
        }

        $cacheKey = 'redirect_loop_check_' . $userId . '_' . md5($request->path());
        $current = Cache::get($cacheKey, 0);
        
        // If same path requested 5+ times in 30 seconds = redirect loop
        if ($current >= 5) {
            Log::error('[Redirect Loop Detected]', [
                'user_id' => $userId,
                'path' => $request->path(),
                'count' => $current,
                'ip' => $request->ip(),
                'referer' => $request->header('referer'),
            ]);

            // Clear the redirect loop flag
            Cache::forget($cacheKey);

            // Logout user and force re-login
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('sso.login')
                ->with('error', 'Sesi Anda mengalami gangguan. Silakan login kembali.');
        }

        // Increment counter for this path
        Cache::put($cacheKey, $current + 1, 30); // 30 seconds expiry

        return $next($request);
    }
}
