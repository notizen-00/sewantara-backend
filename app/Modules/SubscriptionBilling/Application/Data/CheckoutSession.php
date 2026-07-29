<?php

namespace App\Modules\SubscriptionBilling\Application\Data;

readonly class CheckoutSession
{
    public function __construct(
        public string $token,
        public string $redirectUrl,
    ) {}
}
