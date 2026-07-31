<?php

namespace App\Modules\Payments;

use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PaymentGatewayManager::class,
            fn ($app): PaymentGatewayManager => new PaymentGatewayManager(
                container: $app,
                drivers: config('payments.drivers', []),
                default: (string) config('payments.default', 'midtrans'),
            ),
        );
    }
}
