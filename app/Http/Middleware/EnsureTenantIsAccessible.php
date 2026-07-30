<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class EnsureTenantIsAccessible
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $this->tenancy->tenant;

        if (! $this->tenancy->initialized || ! $tenant) {
            return $this->error('TENANT_NOT_FOUND', 'Tenant tidak ditemukan.', 404);
        }

        if (! in_array($tenant->status, ['onboarding', 'active'], true)) {
            $code = $tenant->status === 'suspended'
                ? 'TENANT_SUSPENDED'
                : 'TENANT_INACCESSIBLE';

            return $this->error($code, 'Tenant tidak dapat diakses.', 423);
        }

        return $next($request);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => null,
            ],
        ], $status);
    }
}
