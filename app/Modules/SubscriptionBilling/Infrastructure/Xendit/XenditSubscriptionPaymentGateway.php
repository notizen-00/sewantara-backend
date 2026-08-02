<?php

namespace App\Modules\SubscriptionBilling\Infrastructure\Xendit;

use App\Modules\SubscriptionBilling\Application\Data\CheckoutSession;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class XenditSubscriptionPaymentGateway implements SubscriptionPaymentGateway
{
    public function __construct(
        private readonly XenditPaymentSessionClient $client,
    ) {}

    public function createCheckout(
        string $orderId,
        int $grossAmount,
        array $customer,
        array $items,
    ): CheckoutSession {
        $this->validateCheckout($orderId, $grossAmount, $items);
        $secretKey = (string) config('services.xendit.secret_key');

        if ($secretKey === '') {
            throw new LogicException('XENDIT_SECRET_KEY belum dikonfigurasi.');
        }

        $payload = [
            'reference_id' => $orderId,
            'session_type' => 'PAY',
            'mode' => 'PAYMENT_LINK',
            'amount' => $grossAmount,
            'currency' => 'IDR',
            'country' => 'ID',
            'locale' => 'id',
            'description' => 'Pembayaran langganan Sewantara '.$orderId,
            'items' => array_map(
                fn (array $item): array => [
                    'reference_id' => (string) ($item['id'] ?? $orderId),
                    'name' => (string) ($item['name'] ?? 'Langganan Sewantara'),
                    'type' => 'DIGITAL_SERVICE',
                    'category' => 'SOFTWARE_SUBSCRIPTION',
                    'net_unit_amount' => (int) ($item['price'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'currency' => 'IDR',
                ],
                $items,
            ),
        ];

        $customerName = trim((string) ($customer['name'] ?? $customer['first_name'] ?? ''));
        $customerEmail = trim((string) ($customer['email'] ?? ''));

        if ($customerName !== '' || $customerEmail !== '') {
            $payload['customer'] = [
                'reference_id' => 'customer-'.$orderId,
                'type' => 'INDIVIDUAL',
                ...($customerEmail === '' ? [] : ['email' => $customerEmail]),
                'individual_detail' => [
                    'given_names' => $customerName === '' ? 'Pelanggan Sewantara' : $customerName,
                ],
            ];
        }

        $returnUrls = (array) config('subscription-billing.return_urls', []);

        if (is_string($returnUrls['success'] ?? null) && $returnUrls['success'] !== '') {
            $payload['success_return_url'] = $returnUrls['success'];
        }

        if (is_string($returnUrls['cancel'] ?? null) && $returnUrls['cancel'] !== '') {
            $payload['cancel_return_url'] = $returnUrls['cancel'];
        }

        $response = $this->client->create($secretKey, $payload);

        $sessionId = $response['payment_session_id'] ?? null;
        $redirectUrl = $response['payment_link_url'] ?? null;

        if (! is_string($sessionId) || ! is_string($redirectUrl)) {
            throw new UnexpectedValueException('Respons pembayaran Xendit tidak valid.');
        }

        return new CheckoutSession($sessionId, $redirectUrl);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateCheckout(string $orderId, int $grossAmount, array $items): void
    {
        if ($orderId === '' || strlen($orderId) > 64 || $grossAmount < 1) {
            throw new InvalidArgumentException('Data checkout Xendit tidak valid.');
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
