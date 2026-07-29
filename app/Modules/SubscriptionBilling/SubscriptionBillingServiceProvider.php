<?php

namespace App\Modules\SubscriptionBilling;

use App\Modules\SubscriptionBilling\Contracts\PaidTenantProvisioner;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Events\SubscriptionPaymentPaid;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Tenancy\StanclPaidTenantProvisioner;
use App\Modules\SubscriptionBilling\Listeners\ProvisionTenantAfterPayment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SubscriptionPaymentGateway::class,
            MidtransSubscriptionPaymentGateway::class,
        );
        $this->app->bind(
            PaidTenantProvisioner::class,
            StanclPaidTenantProvisioner::class,
        );
    }

    public function boot(): void
    {
        Event::listen(
            SubscriptionPaymentPaid::class,
            ProvisionTenantAfterPayment::class,
        );
    }
}
