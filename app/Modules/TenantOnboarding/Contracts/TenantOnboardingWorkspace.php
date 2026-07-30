<?php

namespace App\Modules\TenantOnboarding\Contracts;

interface TenantOnboardingWorkspace
{
    public function snapshot(string $tenantId): array;

    public function updateBusiness(string $tenantId, array $attributes): array;

    public function updateRentalConfiguration(string $tenantId, array $attributes): array;

    public function updateBookingConfiguration(string $tenantId, array $attributes): array;

    public function updatePaymentConfiguration(string $tenantId, array $methods): array;

    public function completeInventorySetup(string $tenantId): array;

    public function completePricing(string $tenantId): array;

    public function goLive(string $tenantId): array;
}
