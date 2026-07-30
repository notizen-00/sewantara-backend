<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class EnsureTenantSubscriptionActive
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $this->tenancy->tenant;

        if (! $this->tenancy->initialized || ! $tenant instanceof Tenant) {
            return $this->error('TENANT_NOT_FOUND', 'Akun usaha tidak ditemukan.', 404);
        }

        $subscription = $tenant->planSubscription('main');

        if (! $subscription) {
            return $this->error(
                'SUBSCRIPTION_REQUIRED',
                'Langganan utama belum tersedia.',
                403,
            );
        }

        if ($subscription->inactive()) {
            return $this->error(
                'SUBSCRIPTION_EXPIRED',
                'Masa langganan telah berakhir.',
                423,
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
