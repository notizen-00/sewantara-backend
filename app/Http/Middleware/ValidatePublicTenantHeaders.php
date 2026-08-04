<?php

namespace App\Http\Middleware;

use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePublicTenantHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = strtolower(trim((string) $request->header('X-Tenant')));
        $host = strtolower(rtrim(trim((string) $request->header('X-Tenant-Host')), '.'));

        if (! $this->validSlug($slug) || ! $this->validHost($host)) {
            return PublicApiResponse::error(
                $request,
                'REQUEST_INVALID',
                'Identitas tenant tidak valid.',
                400,
            );
        }

        $request->attributes->set('tenant_slug', $slug);
        $request->attributes->set('tenant_host', $host);

        return $next($request);
    }

    private function validSlug(string $slug): bool
    {
        return strlen($slug) >= 2
            && preg_match(
                '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                $slug,
            ) === 1
            && ! str_contains($slug, '--')
            && ! in_array($slug, config('public-api.reserved_slugs', []), true);
    }

    private function validHost(string $host): bool
    {
        if ($host === ''
            || strlen($host) > 253
            || str_contains($host, '://')
            || str_contains($host, '/')
            || str_contains($host, '@')
            || str_contains($host, ':')
            || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            !== false;
    }
}
