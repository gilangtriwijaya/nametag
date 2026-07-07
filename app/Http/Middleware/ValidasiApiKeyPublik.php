<?php

namespace App\Http\Middleware;

use App\Models\ApiAccessLog;
use Closure;
use Illuminate\Http\Request;

class ValidasiApiKeyPublik
{
    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);
        $apiKey = (string) $request->header('X-Publik-API-Key', '');
        $validKey = (string) config('app.publik_api_key', '');

        if ($validKey === '' || ! hash_equals($validKey, $apiKey)) {
            $this->log($request, 401, $startTime, false);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $response = $next($request);
        $cacheHit = (bool) $request->attributes->get('statpub.cache_hit', false);

        $this->log($request, $response->getStatusCode(), $startTime, $cacheHit);

        return $response;
    }

    private function log(Request $request, int $code, float $start, bool $cacheHit): void
    {
        try {
            ApiAccessLog::create([
                'app_name' => (string) config('app.code', config('app.name')),
                'endpoint' => $request->path(),
                'ip_address' => (string) $request->ip(),
                'request_source' => $request->header('X-Request-Source'),
                'response_code' => $code,
                'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
                'cache_hit' => $cacheHit,
            ]);
        } catch (\Throwable) {
            // ignore logging errors
        }
    }
}
