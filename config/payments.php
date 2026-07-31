<?php

use App\Modules\Payments\Infrastructure\Midtrans\MidtransPaymentGateway;

return [
    'default' => env('PAYMENT_GATEWAY', 'midtrans'),

    'drivers' => [
        'midtrans' => MidtransPaymentGateway::class,
    ],
];
