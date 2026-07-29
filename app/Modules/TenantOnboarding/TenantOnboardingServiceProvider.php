<?php

namespace App\Modules\TenantOnboarding;

use App\Modules\TenantOnboarding\Contracts\ActivePlanCatalog;
use App\Modules\TenantOnboarding\Contracts\TenantEnvironmentProvisioner;
use App\Modules\TenantOnboarding\Contracts\TenantProvisioningRepository;
use App\Modules\TenantOnboarding\Contracts\TransactionManager;
use App\Modules\TenantOnboarding\Contracts\TrialSubscriptionStarter;
use App\Modules\TenantOnboarding\Infrastructure\Persistence\EloquentActivePlanCatalog;
use App\Modules\TenantOnboarding\Infrastructure\Persistence\EloquentTenantProvisioningRepository;
use App\Modules\TenantOnboarding\Infrastructure\Persistence\LaravelTransactionManager;
use App\Modules\TenantOnboarding\Infrastructure\Subscriptions\LaravelCmTrialSubscriptionStarter;
use App\Modules\TenantOnboarding\Infrastructure\Tenancy\StanclTenantEnvironmentProvisioner;
use Illuminate\Support\ServiceProvider;

class TenantOnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivePlanCatalog::class, EloquentActivePlanCatalog::class);
        $this->app->bind(
            TenantProvisioningRepository::class,
            EloquentTenantProvisioningRepository::class,
        );
        $this->app->bind(
            TrialSubscriptionStarter::class,
            LaravelCmTrialSubscriptionStarter::class,
        );
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(
            TenantEnvironmentProvisioner::class,
            StanclTenantEnvironmentProvisioner::class,
        );
    }
}
