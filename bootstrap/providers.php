<?php

use App\Modules\SubscriptionBilling\SubscriptionBillingServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Modules\TenantOnboarding\TenantOnboardingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    TenantOnboardingServiceProvider::class,
    SubscriptionBillingServiceProvider::class,
];
