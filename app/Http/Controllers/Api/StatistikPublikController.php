<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikPublikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StatistikPublikController extends Controller
{
    public function __construct(private StatistikPublikService $service) {}

    public function show(Request $request): JsonResponse
    {
        config(['cache.default' => 'file']);

        $ttl = max(60, (int) config('app.publik_cache_ttl', 3600));
        $refresh = $request->boolean('refresh');
        $cacheKey = $this->service->cacheKey();
        $cacheHit = Cache::store('file')->has($cacheKey);

        try {
            if ($refresh) {
                Cache::store('file')->forget($cacheKey);
                $cacheHit = false;
            }

            $payload = Cache::store('file')->remember($cacheKey, $ttl, function () {
                return $this->service->wrappedPayload();
            });

            $payload['cache_hit'] = $cacheHit;
            $request->attributes->set('statpub.cache_hit', $cacheHit);

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('statpub_api_error', ['msg' => $e->getMessage()]);

            $request->attributes->set('statpub.cache_hit', false);

            return response()->json([
                'success' => false,
                'app' => config('app.code', config('app.name')),
                'resource' => 'statistik',
                'message' => 'Layanan sementara tidak tersedia',
                'generated_at' => now()->toIso8601String(),
            ], 503);
        }
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'app' => config('app.code', config('app.name')),
            'cache_valid' => Cache::store('file')->has($this->service->cacheKey()),
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
