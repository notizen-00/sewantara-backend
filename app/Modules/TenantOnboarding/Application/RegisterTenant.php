<?php

namespace App\Modules\TenantOnboarding\Application;

use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;
use App\Modules\TenantOnboarding\Application\Data\TenantRegistrationResult;
use App\Modules\TenantOnboarding\Application\Exceptions\BillingIntervalUnavailable;
use App\Modules\TenantOnboarding\Contracts\ActivePlanCatalog;
use App\Modules\TenantOnboarding\Contracts\TenantEnvironmentProvisioner;
use App\Modules\TenantOnboarding\Contracts\TenantProvisioningRepository;
use App\Modules\TenantOnboarding\Contracts\TransactionManager;
use App\Modules\TenantOnboarding\Contracts\TrialSubscriptionStarter;

class RegisterTenant
{
    public function __construct(
        private readonly ActivePlanCatalog $plans,
        private readonly TenantProvisioningRepository $tenants,
        private readonly TrialSubscriptionStarter $subscriptions,
        private readonly TransactionManager $transactions,
        private readonly TenantEnvironmentProvisioner $environment,
    ) {}

    public function execute(RegisterTenantCommand $command): TenantRegistrationResult
    {
        $plan = $this->plans->find($command->planId);

        if ($plan->invoiceInterval !== $command->billingInterval) {
            throw new BillingIntervalUnavailable;
        }

        $registration = $this->transactions->run(function () use ($command, $plan): TenantRegistrationResult {
            $tenant = $this->tenants->provision($command);
            $subscription = $this->subscriptions->start($tenant->tenantId, $plan);

            return new TenantRegistrationResult(
                tenantId: $tenant->tenantId,
                tenantName: $tenant->tenantName,
                tenantSlug: $tenant->tenantSlug,
                tenantStatus: $tenant->tenantStatus,
                timezone: $tenant->timezone,
                currency: $tenant->currency,
                domain: $tenant->domain,
                ownerId: $tenant->ownerId,
                ownerName: $tenant->ownerName,
                ownerEmail: $tenant->ownerEmail,
                planSlug: $plan->slug,
                subscriptionStatus: $subscription->status,
                trialEndsAt: $subscription->trialEndsAt,
            );
        });

        $this->environment->provision($registration->tenantId);

        return new TenantRegistrationResult(
            tenantId: $registration->tenantId,
            tenantName: $registration->tenantName,
            tenantSlug: $registration->tenantSlug,
            tenantStatus: 'active',
            timezone: $registration->timezone,
            currency: $registration->currency,
            domain: $registration->domain,
            ownerId: $registration->ownerId,
            ownerName: $registration->ownerName,
            ownerEmail: $registration->ownerEmail,
            planSlug: $registration->planSlug,
            subscriptionStatus: $registration->subscriptionStatus,
            trialEndsAt: $registration->trialEndsAt,
        );
    }
}
