<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ResolvePublicTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->attributes->get('tenant_slug');
        $host = (string) $request->attributes->get('tenant_host');
        $cacheKey = "tenant-resolution:{$slug}:{$host}";
        $cache = null;
        $resolved = null;

        try {
            $cache = Cache::store(config('public-api.resolution_cache_store'));
            $resolved = $cache->get($cacheKey);
        } catch (Throwable $exception) {
            Log::warning('TENANT_RESOLUTION_CACHE_UNAVAILABLE', [
                ...$this->logContext($request),
                'exception' => $exception::class,
            ]);
        }

        if (! is_array($resolved)) {
            try {
                $resolved = $this->resolve($slug, $host);

                if (is_array($resolved) && $cache !== null) {
                    try {
                        $cache->put(
                            $cacheKey,
                            $resolved,
                            max(1, (int) config(
                                'public-api.resolution_cache_ttl',
                                300,
                            )),
                        );
                    } catch (Throwable $exception) {
                        Log::warning('TENANT_RESOLUTION_CACHE_UNAVAILABLE', [
                            ...$this->logContext($request),
                            'exception' => $exception::class,
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                Log::error('TENANT_RESOLUTION_FAILED', [
                    ...$this->logContext($request),
                    'exception' => $exception::class,
                ]);

                return $this->unavailable($request);
            }
        }

        if (! is_array($resolved)) {
            try {
                if (Tenant::query()->where('slug', $slug)->exists()) {
                    Log::warning(
                        'TENANT_HOST_MISMATCH',
                        $this->logContext($request),
                    );
                }
            } catch (Throwable $exception) {
                Log::error('TENANT_RESOLUTION_FAILED', [
                    ...$this->logContext($request),
                    'exception' => $exception::class,
                ]);

                return $this->unavailable($request);
            }

            return $this->notFound($request);
        }

        try {
            $tenant = Tenant::query()->find($resolved['tenant_id'] ?? null);
            $domain = Domain::query()->find($resolved['domain_id'] ?? null);
        } catch (Throwable $exception) {
            Log::error('TENANT_RESOLUTION_FAILED', [
                ...$this->logContext($request),
                'exception' => $exception::class,
            ]);

            return $this->unavailable($request);
        }

        if (! $tenant || ! $domain
            || $tenant->slug !== $slug
            || (string) $domain->tenant_id !== (string) $tenant->getTenantKey()
            || ! in_array(
                strtolower((string) $domain->domain),
                $this->domainCandidates($slug, $host),
                true,
            )) {
            try {
                $cache?->forget($cacheKey);
            } catch (Throwable) {
                // A stale entry is still denied by the checks above.
            }

            Log::warning('TENANT_HOST_MISMATCH', $this->logContext($request));

            return $this->notFound($request);
        }

        $request->attributes->set('resolved_tenant', $tenant);
        $request->attributes->set('resolved_domain', $domain);

        return $next($request);
    }

    /**
     * @return array{tenant_id: string, domain_id: int}|null
     */
    private function resolve(string $slug, string $host): ?array
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            return null;
        }

        $candidates = $this->domainCandidates($slug, $host);
        $domain = Domain::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->whereIn('domain', array_unique($candidates))
            ->first();

        if (! $domain) {
            return null;
        }

        return [
            'tenant_id' => (string) $tenant->getTenantKey(),
            'domain_id' => (int) $domain->getKey(),
        ];
    }

    /**
     * @return list<string>
     */
    private function domainCandidates(string $slug, string $host): array
    {
        $candidates = [$host];
        $baseDomain = strtolower(trim((string) config(
            'public-api.tenant_base_domain',
        ), '.'));

        if ($baseDomain !== '' && $host === "{$slug}.{$baseDomain}") {
            $candidates[] = $slug;
        }

        return array_values(array_unique($candidates));
    }

    private function notFound(Request $request): Response
    {
        return PublicApiResponse::error(
            $request,
            'TENANT_NOT_FOUND',
            'Tenant tidak ditemukan.',
            404,
        );
    }

    private function unavailable(Request $request): Response
    {
        return PublicApiResponse::error(
            $request,
            'TENANT_SERVICE_UNAVAILABLE',
            'Layanan sementara tidak tersedia.',
            503,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function logContext(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get('request_id'),
            'tenant_slug' => $request->attributes->get('tenant_slug'),
            'tenant_host' => $request->attributes->get('tenant_host'),
            'client_ip' => $request->ip(),
        ];
    }
}
