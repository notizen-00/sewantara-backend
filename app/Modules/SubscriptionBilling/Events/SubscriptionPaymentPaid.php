<?php

namespace App\Modules\SubscriptionBilling\Events;

use Illuminate\Foundation\Events\Dispatchable;

readonly class SubscriptionPaymentPaid
{
    use Dispatchable;

    public function __construct(
        public string $paymentId,
        public string $tenantId,
    ) {}
}
