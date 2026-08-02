<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Xendit;

use App\Modules\SubscriptionBilling\Application\Exceptions\SubscriptionGatewayAuthenticationFailed;
use Illuminate\Support\Facades\Http;

class XenditPaymentSessionClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(string $secretKey, array $payload): array
    {
        $baseUrl = rtrim(
            (string) config('services.xendit.base_url', 'https://api.xendit.co'),
            '/',
        );

        $response = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($secretKey, '')
            ->connectTimeout(5)
            ->timeout(15)
            ->post($baseUrl.'/sessions', $payload);

        if ($response->unauthorized()) {
            throw new SubscriptionGatewayAuthenticationFailed;
        }

        return $response->throw()->json();
    }
}
