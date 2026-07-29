<?php

namespace App\Modules\SubscriptionBilling\Contracts;

use App\Modules\SubscriptionBilling\Application\Data\CheckoutSession;

interface SubscriptionPaymentGateway
{
    /**
     * @param  array<string, mixed>  $customer
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createCheckout(
        string $orderId,
        int $grossAmount,
        array $customer,
        array $items,
    ): CheckoutSession;
}
