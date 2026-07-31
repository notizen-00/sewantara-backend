<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\CheckoutRequest;
use App\Modules\Payments\Data\CheckoutSession;
use App\Modules\Payments\Data\GatewayNotification;

interface PaymentGateway
{
    public function code(): string;

    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** @param array<string, mixed> $payload */
    public function parseNotification(array $payload): GatewayNotification;
}
