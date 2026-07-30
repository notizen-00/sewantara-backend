<?php

namespace App\Modules\TenantOnboarding\Application;

use App\Modules\TenantOnboarding\Contracts\TenantOnboardingWorkspace;

class ConfigureTenantOnboarding
{
    public function __construct(
        private readonly TenantOnboardingWorkspace $workspace,
    ) {}

    public function snapshot(string $tenantId): array
    {
        return $this->workspace->snapshot($tenantId);
    }

    public function business(string $tenantId, array $attributes): array
    {
        return $this->workspace->updateBusiness($tenantId, $attributes);
    }

    public function rental(string $tenantId, array $attributes): array
    {
        return $this->workspace->updateRentalConfiguration($tenantId, $attributes);
    }

    public function booking(string $tenantId, array $attributes): array
    {
        return $this->workspace->updateBookingConfiguration($tenantId, $attributes);
    }

    public function payments(string $tenantId, array $methods): array
    {
        return $this->workspace->updatePaymentConfiguration($tenantId, $methods);
    }

    public function inventoryCompleted(string $tenantId): array
    {
        return $this->workspace->completeInventorySetup($tenantId);
    }

    public function pricingCompleted(string $tenantId): array
    {
        return $this->workspace->completePricing($tenantId);
    }

    public function goLive(string $tenantId): array
    {
        return $this->workspace->goLive($tenantId);
    }
}
