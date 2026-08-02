<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;

class GetSubscriptionPayment
{
    public function execute(Tenant $tenant, string $paymentId): SubscriptionPayment
    {
        return SubscriptionPayment::query()
            ->whereKey($paymentId)
            ->where('tenant_id', (string) $tenant->getKey())
            ->firstOrFail();
    }
}
