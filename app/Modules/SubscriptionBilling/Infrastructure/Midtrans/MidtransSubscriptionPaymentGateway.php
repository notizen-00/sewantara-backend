<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Midtrans;

use App\Modules\SubscriptionBilling\Application\Data\CheckoutSession;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use InvalidArgumentException;
use LogicException;
use Midtrans\Config;
use UnexpectedValueException;

class MidtransSubscriptionPaymentGateway implements SubscriptionPaymentGateway
{
    public function __construct(
        private readonly MidtransSnapClient $snap,
    ) {}

    public function createCheckout(
        string $orderId,
        int $grossAmount,
        array $customer,
        array $items,
    ): CheckoutSession {
        if (strlen($orderId) > 50
            || ! preg_match('/^[A-Za-z0-9_~.-]+$/', $orderId)) {
            throw new InvalidArgumentException('Nomor pesanan Midtrans tidak valid.');
        }

        if ($grossAmount < 1) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari nol.');
        }

        $itemTotal = collect($items)->sum(
            fn (array $item): int => (int) ($item['price'] ?? 0)
                * (int) ($item['quantity'] ?? 0),
        );

        if ($items === [] || $itemTotal !== $grossAmount) {
            throw new InvalidArgumentException(
                'Total rincian pembayaran harus sama dengan jumlah keseluruhan.',
            );
        }

        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            throw new LogicException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $this->configureSdk($serverKey, $orderId);

        $response = $this->snap->createTransaction([
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => $customer,
            'item_details' => $items,
        ]);

        if (! is_string($response->token ?? null)
            || ! is_string($response->redirect_url ?? null)) {
            throw new UnexpectedValueException('Respons pembayaran Midtrans tidak valid.');
        }

        return new CheckoutSession(
            token: $response->token,
            redirectUrl: $response->redirect_url,
        );
    }

    private function configureSdk(string $serverKey, string $orderId): void
    {
        Config::$serverKey = $serverKey;
        Config::$clientKey = (string) config('services.midtrans.client_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        Config::$is3ds = (bool) config('services.midtrans.is_3ds', true);
        Config::$isSanitized = true;
        Config::$paymentIdempotencyKey = $orderId;
        Config::$appendNotifUrl = null;
        Config::$overrideNotifUrl = null;
        Config::$curlOptions = [
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ];
    }
}
