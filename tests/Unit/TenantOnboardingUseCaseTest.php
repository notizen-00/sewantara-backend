<?php

use App\Modules\TenantOnboarding\Application\Data\AvailablePlan;
use App\Modules\TenantOnboarding\Application\Data\ProvisionedTenant;
use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;
use App\Modules\TenantOnboarding\Application\Data\StartedSubscription;
use App\Modules\TenantOnboarding\Application\Exceptions\BillingIntervalUnavailable;
use App\Modules\TenantOnboarding\Application\RegisterTenant;
use App\Modules\TenantOnboarding\Contracts\ActivePlanCatalog;
use App\Modules\TenantOnboarding\Contracts\TenantEnvironmentProvisioner;
use App\Modules\TenantOnboarding\Contracts\TenantProvisioningRepository;
use App\Modules\TenantOnboarding\Contracts\TransactionManager;
use App\Modules\TenantOnboarding\Contracts\TrialSubscriptionStarter;

afterEach(fn () => Mockery::close());

test('tenant onboarding activates the tenant immediately for its trial', function () {
    $command = onboardingCommand();
    $plan = new AvailablePlan(1, 'starter', 'month');
    $tenant = provisionedTenant();
    $subscription = new StartedSubscription(
        status: 'trial',
        trialEndsAt: new DateTimeImmutable('+14 days'),
    );

    $plans = Mockery::mock(ActivePlanCatalog::class);
    $plans->shouldReceive('find')->once()->with(1)->andReturn($plan);

    $repository = Mockery::mock(TenantProvisioningRepository::class);
    $repository->shouldReceive('provision')
        ->once()
        ->with($command)
        ->andReturn($tenant);

    $subscriptions = Mockery::mock(TrialSubscriptionStarter::class);
    $subscriptions->shouldReceive('start')
        ->once()
        ->with('tenant-id', $plan)
        ->andReturn($subscription);

    $transactions = Mockery::mock(TransactionManager::class);
    $transactions->shouldReceive('run')
        ->once()
        ->andReturnUsing(fn (Closure $operation) => $operation());

    $environment = Mockery::mock(TenantEnvironmentProvisioner::class);
    $environment->shouldReceive('provision')
        ->once()
        ->with('tenant-id');

    $registered = (new RegisterTenant(
        $plans,
        $repository,
        $subscriptions,
        $transactions,
        $environment,
    ))->execute($command);

    expect($registered->tenantId)->toBe('tenant-id')
        ->and($registered->tenantStatus)->toBe('active')
        ->and($registered->subscriptionStatus)->toBe('trial')
        ->and($registered->planSlug)->toBe('starter');
});

test('tenant onboarding rejects a billing interval unavailable on the plan', function () {
    $command = onboardingCommand(billingInterval: 'year');
    $plan = new AvailablePlan(1, 'starter', 'month');

    $plans = Mockery::mock(ActivePlanCatalog::class);
    $plans->shouldReceive('find')->once()->with(1)->andReturn($plan);

    $repository = Mockery::mock(TenantProvisioningRepository::class);
    $repository->shouldNotReceive('provision');

    $subscriptions = Mockery::mock(TrialSubscriptionStarter::class);
    $subscriptions->shouldNotReceive('start');

    $transactions = Mockery::mock(TransactionManager::class);
    $transactions->shouldNotReceive('run');

    $environment = Mockery::mock(TenantEnvironmentProvisioner::class);
    $environment->shouldNotReceive('provision');

    expect(fn () => (new RegisterTenant(
        $plans,
        $repository,
        $subscriptions,
        $transactions,
        $environment,
    ))->execute($command))->toThrow(BillingIntervalUnavailable::class);
});

function onboardingCommand(string $billingInterval = 'month'): RegisterTenantCommand
{
    return new RegisterTenantCommand(
        businessName: 'Rental Kamera',
        businessType: 'camera_rental',
        subdomain: 'rental-kamera',
        ownerName: 'Owner',
        ownerEmail: 'owner@example.com',
        ownerPhone: '081234567890',
        ownerPassword: 'StrongPassword123!',
        planId: 1,
        billingInterval: $billingInterval,
    );
}

function provisionedTenant(): ProvisionedTenant
{
    return new ProvisionedTenant(
        tenantId: 'tenant-id',
        tenantName: 'Rental Kamera',
        tenantSlug: 'rental-kamera',
        tenantStatus: 'pending',
        timezone: 'Asia/Jakarta',
        currency: 'IDR',
        domain: 'rental-kamera',
        ownerId: 'owner-id',
        ownerName: 'Owner',
        ownerEmail: 'owner@example.com',
    );
}
