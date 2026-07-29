<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Persistence;

use App\Modules\TenantOnboarding\Application\Data\AvailablePlan;
use App\Modules\TenantOnboarding\Contracts\ActivePlanCatalog;

class EloquentActivePlanCatalog implements ActivePlanCatalog
{
    public function find(int $planId): AvailablePlan
    {
        $planModel = config('laravel-subscriptions.models.plan');
        $plan = $planModel::query()
            ->whereKey($planId)
            ->where('is_active', true)
            ->firstOrFail();

        return new AvailablePlan(
            id: (int) $plan->getKey(),
            slug: $plan->slug,
            invoiceInterval: $plan->invoice_interval,
        );
    }
}
