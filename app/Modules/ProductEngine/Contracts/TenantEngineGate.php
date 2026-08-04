<?php

namespace App\Modules\ProductEngine\Contracts;

interface TenantEngineGate
{
    public function isEnabled(string $tenantId, string $engineCode): bool;
}
