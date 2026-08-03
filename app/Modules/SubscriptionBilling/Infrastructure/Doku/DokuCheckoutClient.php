<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Doku;

use App\Modules\SubscriptionBilling\Application\Exceptions\SubscriptionGatewayAuthenticationFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DokuCheckoutClient
{
    public function __construct(
        private readonly DokuSignature $signature,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(string $clientId, string $secretKey, array $payload): array
    {
        $requestTarget = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $requestTimestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $rawBody = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $baseUrl = rtrim(
            (string) config('services.doku.base_url', 'https://api-sandbox.doku.com'),
            '/',
        );

        $response = Http::acceptJson()
            ->withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $requestTimestamp,
                'Signature' => $this->signature->sign(
                    $clientId,
                    $requestId,
                    $requestTimestamp,
                    $requestTarget,
                    $rawBody,
                    $secretKey,
                ),
            ])
            ->withBody($rawBody, 'application/json')
            ->connectTimeout(5)
            ->timeout(15)
            ->post($baseUrl.$requestTarget);

        if (in_array($response->status(), [401, 403], true)
            || ($response->status() === 400
                && $response->json('error.code') === 'invalid_signature')) {
            throw new SubscriptionGatewayAuthenticationFailed;
        }

        return $response->throw()->json();
    }
}
