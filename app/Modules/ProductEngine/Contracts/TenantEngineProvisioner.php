<?php

namespace App\Modules\ProductEngine\Contracts;

interface TenantEngineProvisioner
{
    /**
     * Enables the bundled/free engines (rental, booking) for a newly
     * provisioned tenant at price 0. Idempotent.
     */
    public function enableDefaults(string $tenantId): void;
}
