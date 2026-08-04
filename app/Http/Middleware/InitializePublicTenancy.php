<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\PublicApiResponse;
use App\Support\TenantSchemaGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InitializePublicTenancy
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantSchemaGuard $schemaGuard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('resolved_tenant');

        if (! $tenant instanceof Tenant) {
            return PublicApiResponse::error(
                $request,
                'TENANT_NOT_FOUND',
                'Tenant tidak ditemukan.',
                404,
            );
        }

        try {
            $this->tenancy->initialize($tenant);
            $this->schemaGuard->assertReady($tenant);
        } catch (Throwable $exception) {
            Log::error('TENANT_DATABASE_UNAVAILABLE', [
                'request_id' => PublicApiResponse::requestId($request),
                'tenant_id' => $tenant->getTenantKey(),
                'tenant_slug' => $request->attributes->get('tenant_slug'),
                'exception' => $exception::class,
            ]);
            $this->cleanup($request);

            return PublicApiResponse::error(
                $request,
                'TENANT_SERVICE_UNAVAILABLE',
                'Layanan sementara tidak tersedia.',
                503,
            );
        }

        app()->instance('currentTenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        try {
            return $next($request);
        } finally {
            $this->cleanup($request);
        }
    }

    private function cleanup(Request $request): void
    {
        app()->forgetInstance('currentTenant');
        $request->attributes->remove('tenant');

        try {
            if ($this->tenancy->initialized) {
                $this->tenancy->end();
            }
        } catch (Throwable $exception) {
            Log::critical('TENANT_CONTEXT_CLEANUP_FAILED', [
                'request_id' => PublicApiResponse::requestId($request),
                'exception' => $exception::class,
            ]);
        } finally {
            $central = (string) config(
                'tenancy.database.central_connection',
                config('database.default'),
            );

            DB::purge('tenant');
            config(['database.default' => $central]);
            DB::setDefaultConnection($central);

            $this->tenancy->initialized = false;
            $this->tenancy->tenant = null;
        }
    }
}
