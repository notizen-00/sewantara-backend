<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Xendit;

class XenditWebhookVerifier
{
    public function verify(?string $providedToken): bool
    {
        $expectedToken = (string) config('services.xendit.webhook_token');

        return $expectedToken !== ''
            && is_string($providedToken)
            && hash_equals($expectedToken, $providedToken);
    }
}
