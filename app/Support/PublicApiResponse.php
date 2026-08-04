<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PublicApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        Request $request,
        mixed $data,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => self::meta($request, $meta),
        ], $status);
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @param  array<string, mixed>  $meta
     */
    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        ?array $fields = null,
        array $meta = [],
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($fields !== null) {
            $error['fields'] = $fields;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
            'meta' => self::meta($request, $meta),
        ], $status)->withHeaders([
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function meta(Request $request, array $extra = []): array
    {
        $meta = [
            'request_id' => self::requestId($request),
        ];
        $slug = $request->attributes->get('tenant_slug');

        if (is_string($slug) && $slug !== '') {
            $meta['tenant'] = $slug;
        }

        return array_filter(
            [...$extra, ...$meta],
            static fn (mixed $value): bool => $value !== null,
        );
    }

    public static function requestId(Request $request): string
    {
        $existing = $request->attributes->get('request_id');

        if (is_string($existing) && self::validRequestId($existing)) {
            return $existing;
        }

        $candidate = trim((string) $request->header('X-Request-Id'));
        $requestId = self::validRequestId($candidate)
            ? $candidate
            : (string) Str::ulid();

        $request->attributes->set('request_id', $requestId);

        return $requestId;
    }

    private static function validRequestId(string $requestId): bool
    {
        return Str::isUuid($requestId) || Str::isUlid($requestId);
    }
}
