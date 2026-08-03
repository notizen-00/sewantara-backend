<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Doku;

use Illuminate\Http\Request;

class DokuWebhookVerifier
{
    public function __construct(
        private readonly DokuSignature $signature,
    ) {}

    public function verify(Request $request): bool
    {
        $configuredClientId = trim((string) config('services.doku.client_id'));
        $secretKey = trim((string) config('services.doku.secret_key'));
        $clientId = trim((string) $request->header('Client-Id'));
        $requestId = trim((string) $request->header('Request-Id'));
        $requestTimestamp = trim((string) $request->header('Request-Timestamp'));
        $providedSignature = trim((string) $request->header('Signature'));

        if ($configuredClientId === '' || $secretKey === ''
            || $clientId === '' || $requestId === ''
            || $requestTimestamp === '' || $providedSignature === ''
            || ! hash_equals($configuredClientId, $clientId)) {
            return false;
        }

        return $this->signature->verify(
            $providedSignature,
            $clientId,
            $requestId,
            $requestTimestamp,
            $request->getPathInfo(),
            $request->getContent(),
            $secretKey,
        );
    }
}
