<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Doku;

class DokuSignature
{
    public function sign(
        string $clientId,
        string $requestId,
        string $requestTimestamp,
        string $requestTarget,
        string $rawBody,
        string $secretKey,
    ): string {
        $digest = base64_encode(hash('sha256', $rawBody, true));
        $component = implode("\n", [
            'Client-Id:'.$clientId,
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$requestTimestamp,
            'Request-Target:'.$requestTarget,
            'Digest:'.$digest,
        ]);

        return 'HMACSHA256='.base64_encode(
            hash_hmac('sha256', $component, $secretKey, true),
        );
    }

    public function verify(
        string $signature,
        string $clientId,
        string $requestId,
        string $requestTimestamp,
        string $requestTarget,
        string $rawBody,
        string $secretKey,
    ): bool {
        return hash_equals(
            $this->sign(
                $clientId,
                $requestId,
                $requestTimestamp,
                $requestTarget,
                $rawBody,
                $secretKey,
            ),
            $signature,
        );
    }
}
