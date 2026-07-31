<?php

namespace App\Modules\Payments\Infrastructure\Midtrans;

readonly class MidtransCredentials
{
    public function __construct(
        public string $serverKey,
        public string $clientKey,
        public bool $production,
        public bool $secure3ds,
    ) {}
}
