<?php

namespace App\Modules\TenantOnboarding\Contracts;

use App\Modules\TenantOnboarding\Application\Data\ProvisionedTenant;
use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;

interface TenantProvisioningRepository
{
    public function provision(RegisterTenantCommand $command): ProvisionedTenant;
}
