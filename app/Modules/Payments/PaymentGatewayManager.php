<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @param array<string, class-string<PaymentGateway>> $drivers */
    public function __construct(
        private readonly Container $container,
        private readonly array $drivers,
        private readonly string $default,
    ) {}

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= $this->default;
        $class = $this->drivers[$name] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Payment gateway [{$name}] tidak didukung.");
        }

        $gateway = $this->container->make($class);

        if (! $gateway instanceof PaymentGateway || $gateway->code() !== $name) {
            throw new InvalidArgumentException("Konfigurasi payment gateway [{$name}] tidak valid.");
        }

        return $gateway;
    }
}
