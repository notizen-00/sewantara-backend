<?php

namespace App\Modules\TenantOnboarding\Contracts;

interface TenantEnvironmentProvisioner
{
    public function provision(string $tenantId): void;
}
