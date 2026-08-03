<?php

use App\Modules\SubscriptionBilling\Infrastructure\Doku\DokuSubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditSubscriptionPaymentGateway;

return [
    'default' => env('SUBSCRIPTION_PAYMENT_GATEWAY', 'xendit'),

    'return_urls' => [
        'success' => env('SUBSCRIPTION_PAYMENT_SUCCESS_URL'),
        'cancel' => env('SUBSCRIPTION_PAYMENT_CANCEL_URL'),
    ],

    'drivers' => [
        'doku' => DokuSubscriptionPaymentGateway::class,
        'midtrans' => MidtransSubscriptionPaymentGateway::class,
        'xendit' => XenditSubscriptionPaymentGateway::class,
    ],
];
