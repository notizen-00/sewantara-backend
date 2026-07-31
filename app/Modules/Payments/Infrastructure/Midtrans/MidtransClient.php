<?php

namespace App\Modules\Payments\Infrastructure\Midtrans;

use Midtrans\Snap;

class MidtransClient
{
    /** @param array<string, mixed> $parameters */
    public function createTransaction(array $parameters): object
    {
        return Snap::createTransaction($parameters);
    }
}
