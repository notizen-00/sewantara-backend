<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Midtrans;

class MidtransSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signature = $payload['signature_key'] ?? null;

        if ($serverKey === ''
            || ! is_string($orderId)
            || ! is_string($statusCode)
            || ! is_string($grossAmount)
            || ! is_string($signature)) {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }
}
