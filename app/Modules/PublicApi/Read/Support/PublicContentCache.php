<?php

namespace App\Modules\PublicApi\Read\Support;

use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class PublicContentCache
{
    public function remember(
        Request $request,
        string $endpoint,
        array $vary,
        Closure $callback,
    ): mixed {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return $callback();
        }

        $callbackStarted = false;
        $callbackCompleted = false;
        $resolved = null;

        try {
            $repository = $this->repository();
            $tenantId = (string) $tenant->getTenantKey();
            $versionKey = "public:v1:{$tenantId}:content-version";
            $version = $repository->rememberForever(
                $versionKey,
                fn (): string => (string) Str::ulid(),
            );
            $dimensions = [
                'tenant_id' => $tenantId,
                'host' => (string) $request->attributes->get('tenant_host'),
                'locale' => (string) $tenant->locale,
                'currency' => (string) $tenant->currency,
                'endpoint' => $endpoint,
                'version' => $version,
                'vary' => $this->normalize($vary),
            ];
            $hash = hash(
                'sha256',
                json_encode($dimensions, JSON_THROW_ON_ERROR),
            );

            return $repository->remember(
                "public:v1:{$tenantId}:{$endpoint}:{$hash}",
                max(1, (int) config('public-api.content_cache_ttl', 300)),
                function () use (
                    $callback,
                    &$callbackStarted,
                    &$callbackCompleted,
                    &$resolved,
                ): mixed {
                    $callbackStarted = true;
                    $resolved = $callback();
                    $callbackCompleted = true;

                    return $resolved;
                },
            );
        } catch (Throwable $exception) {
            if ($callbackCompleted) {
                report($exception);

                return $resolved;
            }

            if ($callbackStarted) {
                throw $exception;
            }

            report($exception);

            return $callback();
        }
    }

    public function invalidate(Tenant|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant
            ? (string) $tenant->getTenantKey()
            : $tenant;

        try {
            $this->repository()->forever(
                "public:v1:{$tenantId}:content-version",
                (string) Str::ulid(),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function repository(): Repository
    {
        $store = config('public-api.content_cache_store', config('cache.default'));

        return Cache::store(is_string($store) ? $store : null);
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
