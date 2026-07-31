<?php

namespace App\Modules\Payments\Infrastructure\Midtrans;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\CheckoutRequest;
use App\Modules\Payments\Data\CheckoutSession;
use App\Modules\Payments\Data\GatewayNotification;
use InvalidArgumentException;
use Midtrans\Config;
use UnexpectedValueException;

class MidtransPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly MidtransClient $client,
        private readonly MidtransCredentialResolver $credentials,
    ) {}

    public function code(): string
    {
        return 'midtrans';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $this->validateCheckout($request);
        $this->configure($this->credentials->resolve(), $request);

        $response = $this->client->createTransaction([
            'transaction_details' => [
                'order_id' => $request->orderId,
                'gross_amount' => $request->grossAmount,
            ],
            'customer_details' => $request->customer,
            'item_details' => $request->items,
        ]);

        if (! is_string($response->token ?? null)
            || ! is_string($response->redirect_url ?? null)) {
            throw new UnexpectedValueException('Respons pembayaran Midtrans tidak valid.');
        }

        return new CheckoutSession(
            gateway: $this->code(),
            token: $response->token,
            redirectUrl: $response->redirect_url,
        );
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        $credentials = $this->credentials->resolve();
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $amount = $payload['gross_amount'] ?? null;
        $signature = $payload['signature_key'] ?? null;

        if (! is_string($orderId) || ! is_string($statusCode)
            || ! is_string($amount) || ! is_string($signature)) {
            throw new InvalidArgumentException('Notifikasi Midtrans tidak lengkap.');
        }

        $expected = hash('sha512', $orderId.$statusCode.$amount.$credentials->serverKey);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Tanda tangan digital Midtrans tidak valid.');
        }

        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');
        $status = match (true) {
            $transactionStatus === 'settlement',
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => 'paid',
            $transactionStatus === 'expire' => 'expired',
            in_array($transactionStatus, ['deny', 'cancel', 'failure'], true) => 'failed',
            default => 'pending',
        };

        return new GatewayNotification(
            orderId: $orderId,
            transactionId: isset($payload['transaction_id'])
                ? (string) $payload['transaction_id']
                : null,
            amount: $amount,
            status: $status,
            payload: $payload,
        );
    }

    private function validateCheckout(CheckoutRequest $request): void
    {
        if ($request->currency !== 'IDR' || $request->grossAmount < 1
            || strlen($request->orderId) > 50
            || ! preg_match('/^[A-Za-z0-9_~.-]+$/', $request->orderId)) {
            throw new InvalidArgumentException('Data checkout Midtrans tidak valid.');
        }

        $total = collect($request->items)->sum(
            fn (array $item): int => (int) ($item['price'] ?? 0)
                * (int) ($item['quantity'] ?? 0),
        );

        if ($request->items === [] || $total !== $request->grossAmount) {
            throw new InvalidArgumentException('Total rincian pembayaran tidak sesuai.');
        }
    }

    private function configure(MidtransCredentials $credentials, CheckoutRequest $request): void
    {
        Config::$serverKey = $credentials->serverKey;
        Config::$clientKey = $credentials->clientKey;
        Config::$isProduction = $credentials->production;
        Config::$is3ds = $credentials->secure3ds;
        Config::$isSanitized = true;
        Config::$paymentIdempotencyKey = $request->orderId;
        Config::$appendNotifUrl = null;
        Config::$overrideNotifUrl = $request->notificationUrl;
        Config::$curlOptions = [CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15];
    }
}
