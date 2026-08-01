<?php

namespace App\Modules\SubscriptionBilling;

use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SubscriptionPaymentGateway::class,
            function ($app): SubscriptionPaymentGateway {
                $name = (string) config('subscription-billing.default', 'xendit');
                $class = config("subscription-billing.drivers.{$name}");

                if (! is_string($class)) {
                    throw new InvalidArgumentException(
                        "Subscription payment gateway [{$name}] tidak didukung.",
                    );
                }

                $gateway = $app->make($class);

                if (! $gateway instanceof SubscriptionPaymentGateway) {
                    throw new InvalidArgumentException(
                        "Subscription payment gateway [{$name}] tidak valid.",
                    );
                }

                return $gateway;
            },
        );
    }
}
