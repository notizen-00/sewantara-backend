<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ObservePublicApiRequest
{
    private const START_ATTRIBUTE = '_public_api_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->observed($request)) {
            $request->attributes->set(self::START_ATTRIBUTE, hrtime(true));
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get(self::START_ATTRIBUTE);

        if (! is_int($startedAt)) {
            return;
        }

        $tenant = $request->attributes->get('resolved_tenant');
        $route = $request->route();

        Log::info('PUBLIC_API_REQUEST', [
            'request_id' => PublicApiResponse::requestId($request),
            'tenant_id' => $tenant instanceof Tenant
                ? (string) $tenant->getTenantKey()
                : null,
            'tenant_slug' => $request->attributes->get('tenant_slug'),
            'tenant_host' => $request->attributes->get('tenant_host'),
            'route' => is_object($route) && method_exists($route, 'getName')
                ? $route->getName()
                : null,
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'client_ip' => $request->ip(),
            'bff_service_id' => $request->attributes->get('bff_service_id')
                ?? $request->attributes->get('internal_service_id'),
            'user_id' => $request->attributes->get(
                'authenticated_customer_id',
            ),
        ]);
    }

    private function observed(Request $request): bool
    {
        return $request->is('v1/public')
            || $request->is('v1/public/*')
            || $request->is('healthz')
            || $request->is('readyz');
    }
}
