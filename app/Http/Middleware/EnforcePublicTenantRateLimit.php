<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnforcePublicTenantRateLimit
{
    public function handle(Request $request, Closure $next, string $group = 'read'): Response
    {
        $definition = config("public-api.rate_limits.{$group}", []);
        $maxAttempts = max(1, (int) ($definition['max_attempts'] ?? 60));
        $decay = max(1, (int) ($definition['decay_seconds'] ?? 60));
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant
            ? (string) $tenant->getTenantKey()
            : 'unresolved';
        $key = implode(':', [
            'public-api',
            $tenantId,
            $group,
            $request->ip(),
        ]);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            $response = PublicApiResponse::error(
                $request,
                'RATE_LIMITED',
                'Terlalu banyak permintaan.',
                429,
                meta: ['retry_after' => $retryAfter],
            );
            $response->headers->set('Retry-After', (string) $retryAfter);

            return $response;
        }

        RateLimiter::hit($key, $decay);
        $response = $next($request);
        $response->headers->set(
            'X-RateLimit-Remaining',
            (string) RateLimiter::remaining($key, $maxAttempts),
        );

        return $response;
    }
}
