<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Support\PublicApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class PublicReadController extends Controller
{
    protected function cached(
        Request $request,
        mixed $data,
        array $meta = [],
    ): JsonResponse {
        $response = PublicApiResponse::success($request, $data, $meta);
        $ttl = max(1, (int) config('public-api.content_cache_ttl', 300));
        $response->headers->set(
            'Cache-Control',
            'public, max-age='.min(60, $ttl)
                .', s-maxage='.$ttl
                .', stale-while-revalidate=60',
        );
        $response->headers->set(
            'Vary',
            'X-Tenant-Host, X-Tenant, Accept-Language',
        );

        return $response;
    }

    protected function noStore(
        Request $request,
        mixed $data,
        array $meta = [],
    ): JsonResponse {
        $response = PublicApiResponse::success($request, $data, $meta);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    protected function notFound(Request $request): JsonResponse
    {
        $response = PublicApiResponse::error(
            $request,
            'RESOURCE_NOT_FOUND',
            'Resource tidak ditemukan.',
            404,
        );
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @return array<string, int|bool> */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
