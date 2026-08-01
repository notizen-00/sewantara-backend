<?php

use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditSubscriptionPaymentGateway;

return [
    'default' => env('SUBSCRIPTION_PAYMENT_GATEWAY', 'xendit'),

    'drivers' => [
        'midtrans' => MidtransSubscriptionPaymentGateway::class,
        'xendit' => XenditSubscriptionPaymentGateway::class,
    ],
];
