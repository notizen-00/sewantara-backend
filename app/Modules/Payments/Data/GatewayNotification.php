<?php

namespace App\Modules\Payments\Data;

readonly class GatewayNotification
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $orderId,
        public ?string $transactionId,
        public string $amount,
        public string $status,
        public array $payload,
    ) {}
}
