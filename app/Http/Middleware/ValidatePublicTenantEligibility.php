<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravelcm\Subscriptions\Models\Subscription;
use Symfony\Component\HttpFoundation\Response;

class ValidatePublicTenantEligibility
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('resolved_tenant');
        $domain = $request->attributes->get('resolved_domain');

        if (! $tenant instanceof Tenant || ! $domain instanceof Domain) {
            return PublicApiResponse::error(
                $request,
                'TENANT_NOT_FOUND',
                'Tenant tidak ditemukan.',
                404,
            );
        }

        if ($tenant->status === 'maintenance') {
            $response = PublicApiResponse::error(
                $request,
                'TENANT_MAINTENANCE',
                'Website sedang dalam pemeliharaan.',
                503,
                meta: ['retry_after' => 300],
            );
            $response->headers->set('Retry-After', '300');

            return $response;
        }

        if ($tenant->status === 'suspended') {
            return PublicApiResponse::error(
                $request,
                'TENANT_SUSPENDED',
                'Website tenant sementara tidak tersedia.',
                403,
            );
        }

        if ($tenant->status !== 'active') {
            return PublicApiResponse::error(
                $request,
                'TENANT_NOT_FOUND',
                'Tenant tidak ditemukan.',
                404,
            );
        }

        if ($tenant->provisioning_status !== 'provisioned'
            || $tenant->provisioned_at === null) {
            return PublicApiResponse::error(
                $request,
                'TENANT_SERVICE_UNAVAILABLE',
                'Layanan sementara tidak tersedia.',
                503,
            );
        }

        if (! config('public-api.enabled', true) || ! $tenant->public_web_enabled) {
            return PublicApiResponse::error(
                $request,
                'TENANT_PUBLIC_WEB_DISABLED',
                'Website tenant tidak tersedia.',
                403,
            );
        }

        if (! in_array($domain->status, ['verified', 'active'], true)
            || ($domain->type === 'custom_domain' && $domain->verified_at === null)) {
            return PublicApiResponse::error(
                $request,
                'TENANT_NOT_FOUND',
                'Tenant tidak ditemukan.',
                404,
            );
        }

        $subscription = $tenant->planSubscription('main');

        if ($subscription instanceof Subscription
            && $subscription->inactive()
            && ! $subscription->canceled()
            && $this->withinGracePeriod($subscription)
            && config('public-api.grace_period_public_read', true)
            && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        if (! $subscription || $subscription->inactive()) {
            if (! config('public-api.expired_subscription_public_read', false)
                || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
                return PublicApiResponse::error(
                    $request,
                    'TENANT_SUBSCRIPTION_EXPIRED',
                    'Langganan website tenant telah berakhir.',
                    403,
                );
            }
        }

        return $next($request);
    }

    private function withinGracePeriod(Subscription $subscription): bool
    {
        if ($subscription->ends_at === null) {
            return false;
        }

        $subscription->loadMissing('plan');
        $plan = $subscription->plan;
        $period = max(0, (int) ($plan?->grace_period ?? 0));
        $interval = (string) ($plan?->grace_interval ?? '');

        if ($period === 0) {
            return false;
        }

        $graceEndsAt = $subscription->ends_at->copy();

        match ($interval) {
            'day' => $graceEndsAt->addDays($period),
            'month' => $graceEndsAt->addMonthsNoOverflow($period),
            'year' => $graceEndsAt->addYearsNoOverflow($period),
            default => null,
        };

        return in_array($interval, ['day', 'month', 'year'], true)
            && now()->lt($graceEndsAt);
    }
}
