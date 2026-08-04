<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Modules\ProductEngine\Contracts\TenantEngineGate;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class EnsureTenantEngineEnabled
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantEngineGate $engines,
    ) {}

    public function handle(Request $request, Closure $next, string $engineCode): mixed
    {
        $tenant = $this->tenancy->tenant;

        if (! $this->tenancy->initialized || ! $tenant instanceof Tenant) {
            return $this->error('TENANT_NOT_FOUND', 'Akun usaha tidak ditemukan.', 404);
        }

        if (! $this->engines->isEnabled((string) $tenant->getTenantKey(), $engineCode)) {
            return $this->error(
                'ENGINE_NOT_ENABLED',
                "Engine {$engineCode} belum diaktifkan untuk akun usaha ini.",
                403,
            );
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
