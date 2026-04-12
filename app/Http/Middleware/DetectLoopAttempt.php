<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DetectLoopAttempt
{
    /**
     * Detect redirect loop attempts and break them
     * Much simpler than before - just track "redirect back to self" pattern
     */
    public function handle(Request $request, Closure $next)
    {
        // Only for authenticated users
        if (!auth()->check()) {
            return $next($request);
        }

        $userId = auth()->id();
        $path = $request->path();
        
        // Track if this exact path was just requested (in last 2 seconds)
        $cacheKey = "last_path_{$userId}";
        $lastPath = cache()->get($cacheKey);

        if ($lastPath === $path) {
            // Same path requested twice within 2 seconds = likely loop
            $attempts = cache()->increment("loop_attempts_{$userId}_{$path}", 1, 60);
            
            if ($attempts >= 3) {
                // User requesting same path 3+ times = likely loop
                Log::error('[DetectLoopAttempt] Loop detected', [
                    'user_id' => $userId,
                    'path' => $path,
                    'attempts' => $attempts,
                ]);

                // Clear flags and logout to break loop
                cache()->forget("loop_attempts_{$userId}_{$path}");
                cache()->forget($cacheKey);
                
                auth()->logout();
                $request->session()->invalidate();
                
                return redirect()->route('sso.login')
                    ->with('error', 'Sesi Anda mengalami masalah. Silakan login kembali.');
            }
        } else {
            // Different path = reset counter
            cache()->put($cacheKey, $path, 2); // Remember for 2 seconds
            cache()->forget("loop_attempts_{$userId}_{$path}");
        }

        return $next($request);
    }
}
