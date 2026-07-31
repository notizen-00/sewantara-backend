<?php

namespace App\Modules\Payments\Data;

readonly class CheckoutRequest
{
    /**
     * @param  array<string, mixed>  $customer
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public string $orderId,
        public int $grossAmount,
        public string $currency,
        public array $customer,
        public array $items,
        public string $notificationUrl,
    ) {}
}
