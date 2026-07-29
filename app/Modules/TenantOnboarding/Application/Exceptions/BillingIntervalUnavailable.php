<?php

namespace App\Modules\TenantOnboarding\Application\Exceptions;

use DomainException;

class BillingIntervalUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('Interval pembayaran tidak tersedia untuk plan ini.');
    }
}
