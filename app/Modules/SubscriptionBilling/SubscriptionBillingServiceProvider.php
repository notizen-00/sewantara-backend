<?php

namespace App\Modules\SubscriptionBilling;

use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSubscriptionPaymentGateway;
use Illuminate\Support\ServiceProvider;

class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SubscriptionPaymentGateway::class,
            MidtransSubscriptionPaymentGateway::class,
        );
    }
}
