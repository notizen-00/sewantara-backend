<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Subscriptions;

use App\Models\Tenant;
use App\Modules\TenantOnboarding\Application\Data\AvailablePlan;
use App\Modules\TenantOnboarding\Application\Data\StartedSubscription;
use App\Modules\TenantOnboarding\Contracts\TrialSubscriptionStarter;
use Laravelcm\Subscriptions\Models\Plan;
use LogicException;

class LaravelCmTrialSubscriptionStarter implements TrialSubscriptionStarter
{
    public function start(
        string $tenantId,
        AvailablePlan $plan,
    ): StartedSubscription {
        $tenant = Tenant::query()->findOrFail($tenantId);

        if ($tenant->planSubscription('main')) {
            throw new LogicException('Akun usaha sudah memiliki langganan utama.');
        }

        /** @var Plan $planModel */
        $planModelClass = config('laravel-subscriptions.models.plan');
        $planModel = $planModelClass::query()
            ->whereKey($plan->id)
            ->where('is_active', true)
            ->firstOrFail();

        $subscription = $tenant->newPlanSubscription('main', $planModel);

        return new StartedSubscription(
            status: $subscription->onTrial() ? 'trial' : 'active',
            trialEndsAt: $subscription->trial_ends_at?->toImmutable(),
        );
    }
}
