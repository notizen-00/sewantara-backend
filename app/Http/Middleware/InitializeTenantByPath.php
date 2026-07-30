<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantByPath
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly PathTenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = $this->resolver->resolve($request->route());
        } catch (TenantCouldNotBeIdentifiedByPathException) {
            return $this->tenantNotFound();
        }

        $this->tenancy->initialize($tenant);
        app()->instance('currentTenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        try {
            return $next($request);
        } finally {
            app()->forgetInstance('currentTenant');
            $this->tenancy->end();
        }
    }

    private function tenantNotFound(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'Tenant tidak ditemukan.',
            ],
        ], 404);
    }
}
