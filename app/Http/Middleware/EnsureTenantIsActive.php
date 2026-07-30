<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class EnsureTenantIsActive
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $this->tenancy->tenant;

        if (! $this->tenancy->initialized || ! $tenant) {
            return $this->error('TENANT_NOT_FOUND', 'Akun usaha tidak ditemukan.', 404);
        }

        if ($tenant->status !== 'active') {
            if ($tenant->status === 'onboarding') {
                return $this->error(
                    'TENANT_ONBOARDING_REQUIRED',
                    'Selesaikan penyiapan awal sebelum menggunakan fitur operasional.',
                    423,
                );
            }

            $code = $tenant->status === 'suspended'
                ? 'TENANT_SUSPENDED'
                : 'TENANT_INACTIVE';

            return $this->error($code, 'Akun usaha sedang tidak aktif.', 423);
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
