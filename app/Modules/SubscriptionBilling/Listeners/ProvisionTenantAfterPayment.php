<?php

namespace App\Modules\SubscriptionBilling\Listeners;

use App\Modules\SubscriptionBilling\Contracts\PaidTenantProvisioner;
use App\Modules\SubscriptionBilling\Events\SubscriptionPaymentPaid;

class ProvisionTenantAfterPayment
{
    public function __construct(
        private readonly PaidTenantProvisioner $provisioner,
    ) {}

    public function handle(SubscriptionPaymentPaid $event): void
    {
        $this->provisioner->provision($event->tenantId);
    }
}
