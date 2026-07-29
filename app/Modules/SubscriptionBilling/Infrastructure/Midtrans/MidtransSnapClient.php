<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Midtrans;

use Midtrans\Snap;

class MidtransSnapClient
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function createTransaction(array $parameters): object
    {
        return Snap::createTransaction($parameters);
    }
}
