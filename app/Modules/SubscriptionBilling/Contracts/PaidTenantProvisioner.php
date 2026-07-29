<?php

namespace App\Modules\SubscriptionBilling\Contracts;

interface PaidTenantProvisioner
{
    public function provision(string $tenantId): void;
}
