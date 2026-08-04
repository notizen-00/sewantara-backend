<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApplyPublicResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isTenantPublicApi($request)) {
            return $response;
        }

        self::applyTenantVary($response);

        if ($response->getStatusCode() >= 400
            || ! in_array($request->method(), ['GET', 'HEAD'], true)
            || ! $this->cacheableRoute($request)) {
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        $response->setPublic();
        $response->setMaxAge(max(0, (int) config(
            'public-api.response_cache.max_age',
            60,
        )));
        $response->setSharedMaxAge(max(0, (int) config(
            'public-api.response_cache.shared_max_age',
            300,
        )));
        $stale = max(0, (int) config(
            'public-api.response_cache.stale_while_revalidate',
            60,
        ));

        if ($stale > 0) {
            $response->headers->addCacheControlDirective(
                'stale-while-revalidate',
                $stale,
            );
        }

        return $response;
    }

    public static function applyTenantVary(Response $response): void
    {
        $headers = array_values(array_unique([
            ...$response->getVary(),
            'Accept',
            'X-Tenant',
            'X-Tenant-Host',
        ]));

        $response->headers->set('Vary', implode(', ', $headers));
    }

    private function isTenantPublicApi(Request $request): bool
    {
        return $request->is('v1/public') || $request->is('v1/public/*');
    }

    private function cacheableRoute(Request $request): bool
    {
        $route = $request->route();
        $name = is_object($route) && method_exists($route, 'getName')
            ? $route->getName()
            : null;
        $patterns = config(
            'public-api.response_cache.cacheable_routes',
            [],
        );

        if (! is_string($name) || ! is_array($patterns)) {
            return false;
        }

        return collect($patterns)->contains(
            fn (mixed $pattern): bool => is_string($pattern)
                && Str::is($pattern, $name),
        );
    }
}
