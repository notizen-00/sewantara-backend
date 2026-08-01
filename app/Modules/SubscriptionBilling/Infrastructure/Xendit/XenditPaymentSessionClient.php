<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Xendit;

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

        return Http::acceptJson()
            ->asJson()
            ->withBasicAuth($secretKey, '')
            ->connectTimeout(5)
            ->timeout(15)
            ->post($baseUrl.'/sessions', $payload)
            ->throw()
            ->json();
    }
}
