<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Autentikasi diperlukan.',
                ],
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_INACTIVE',
                    'message' => 'Akun pengguna sedang tidak aktif.',
                ],
            ], 403);
        }

        $tenant = $this->tenancy->tenant;

        if ($this->tenancy->initialized
            && $tenant
            && $user->tenant_id !== $tenant->getTenantKey()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_ACCESS_DENIED',
                    'message' => 'Anda tidak memiliki akses ke akun usaha ini.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
