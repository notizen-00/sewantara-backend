<?php

namespace App\Modules\TenantOnboarding\Contracts;

use App\Modules\TenantOnboarding\Application\Data\AvailablePlan;

interface ActivePlanCatalog
{
    public function find(int $planId): AvailablePlan;
}
