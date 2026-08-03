<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Doku;

use App\Modules\SubscriptionBilling\Application\Data\CheckoutSession;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class DokuSubscriptionPaymentGateway implements SubscriptionPaymentGateway
{
    public function __construct(
        private readonly DokuCheckoutClient $client,
    ) {}

    public function createCheckout(
        string $orderId,
        int $grossAmount,
        array $customer,
        array $items,
    ): CheckoutSession {
        $this->validateCheckout($orderId, $grossAmount, $items);
        $clientId = trim((string) config('services.doku.client_id'));
        $secretKey = trim((string) config('services.doku.secret_key'));

        if ($clientId === '' || $secretKey === '') {
            throw new LogicException('DOKU_CLIENT_ID dan DOKU_SECRET_KEY belum dikonfigurasi.');
        }

        $payload = [
            'order' => [
                'amount' => $grossAmount,
                'invoice_number' => $orderId,
                'currency' => 'IDR',
                'line_items' => array_map(
                    fn (array $item): array => [
                        'id' => (string) ($item['id'] ?? $orderId),
                        'name' => (string) ($item['name'] ?? 'Langganan Sewantara'),
                        'price' => (int) ($item['price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 0),
                    ],
                    $items,
                ),
            ],
            'payment' => [
                'payment_due_date' => max(
                    1,
                    (int) config('services.doku.payment_due_minutes', 60),
                ),
            ],
        ];

        $name = trim((string) ($customer['name'] ?? $customer['first_name'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));

        if ($name !== '' || $email !== '') {
            $payload['customer'] = array_filter([
                'name' => $name,
                'email' => $email,
            ], fn (string $value): bool => $value !== '');
        }

        $returnUrls = (array) config('subscription-billing.return_urls', []);
        $successUrl = $returnUrls['success'] ?? null;
        $cancelUrl = $returnUrls['cancel'] ?? null;

        if (is_string($successUrl) && $successUrl !== '') {
            $payload['order']['callback_url'] = $successUrl;
            $payload['order']['callback_url_result'] = $successUrl;
            $payload['order']['auto_redirect'] = true;
        }

        if (is_string($cancelUrl) && $cancelUrl !== '') {
            $payload['order']['callback_url_cancel'] = $cancelUrl;
        }

        $notificationUrl = config('services.doku.notification_url');

        if (is_string($notificationUrl) && $notificationUrl !== '') {
            $payload['additional_info']['override_notification_url'] = $notificationUrl;
        }

        $response = $this->client->create($clientId, $secretKey, $payload);
        $sessionId = data_get($response, 'response.order.session_id');
        $tokenId = data_get($response, 'response.payment.token_id');
        $redirectUrl = data_get($response, 'response.payment.url');
        $token = is_string($sessionId) && $sessionId !== '' ? $sessionId : $tokenId;

        if (! is_string($token) || $token === ''
            || ! is_string($redirectUrl) || $redirectUrl === '') {
            throw new UnexpectedValueException('Respons pembayaran DOKU tidak valid.');
        }

        return new CheckoutSession($token, $redirectUrl);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateCheckout(string $orderId, int $grossAmount, array $items): void
    {
        if ($orderId === '' || strlen($orderId) > 64 || $grossAmount < 1) {
            throw new InvalidArgumentException('Data checkout DOKU tidak valid.');
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
    }
}
