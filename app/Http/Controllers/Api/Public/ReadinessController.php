<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Support\PublicApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $requestId = PublicApiResponse::requestId($request);
        $cacheKey = 'readiness-probe:'.$requestId;
        $cache = null;
        $ready = false;

        try {
            DB::connection(config('tenancy.database.central_connection'))
                ->select('SELECT 1');

            $cache = Cache::store(config('public-api.readiness_cache_store'));
            $cache->put($cacheKey, $requestId, 5);
            $ready = $cache->get($cacheKey) === $requestId;

            if (! $ready) {
                Log::warning('READINESS_CACHE_PROBE_FAILED', [
                    'request_id' => $requestId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('READINESS_CHECK_FAILED', [
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);
        } finally {
            if ($cache !== null) {
                try {
                    $cache->forget($cacheKey);
                } catch (Throwable $exception) {
                    Log::warning('READINESS_CACHE_CLEANUP_FAILED', [
                        'request_id' => $requestId,
                        'exception' => $exception::class,
                    ]);
                }
            }
        }

        return $ready
            ? PublicApiResponse::success(
                $request,
                ['status' => 'ready'],
            )->withHeaders(['Cache-Control' => 'no-store, private'])
            : PublicApiResponse::error(
                $request,
                'INTERNAL_ERROR',
                'Layanan belum siap.',
                503,
            );
    }
}
