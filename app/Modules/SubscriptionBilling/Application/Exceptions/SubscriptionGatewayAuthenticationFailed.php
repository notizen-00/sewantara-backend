<?php

namespace App\Modules\SubscriptionBilling\Application\Exceptions;

use RuntimeException;

class SubscriptionGatewayAuthenticationFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Autentikasi payment gateway subscription gagal.');
    }
}
