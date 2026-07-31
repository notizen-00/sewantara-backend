<?php

namespace App\Modules\Payments\Data;

use App\Models\Payment;

readonly class BookingPaymentCheckout
{
    public function __construct(
        public Payment $payment,
        public CheckoutSession $checkout,
    ) {}
}
