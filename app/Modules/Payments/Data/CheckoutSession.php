<?php

namespace App\Modules\Payments\Data;

readonly class CheckoutSession
{
    public function __construct(
        public string $gateway,
        public string $token,
        public string $redirectUrl,
    ) {}
}
