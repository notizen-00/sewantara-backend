<?php

namespace App\Modules\TenantOnboarding\Contracts;

use App\Modules\TenantOnboarding\Application\Data\AvailablePlan;
use App\Modules\TenantOnboarding\Application\Data\StartedSubscription;

interface TrialSubscriptionStarter
{
    public function start(
        string $tenantId,
        AvailablePlan $plan,
    ): StartedSubscription;
}
