<?php

use App\Modules\SubscriptionBilling\Application\ConfirmSubscriptionPayment;
use App\Modules\SubscriptionBilling\Application\Exceptions\SubscriptionGatewayAuthenticationFailed;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Doku\DokuCheckoutClient;
use App\Modules\SubscriptionBilling\Infrastructure\Doku\DokuSignature;
use App\Modules\SubscriptionBilling\Infrastructure\Doku\DokuSubscriptionPaymentGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set([
        'subscription-billing.default' => 'doku',
        'subscription-billing.return_urls.success' => 'https://app.example.test/billing/success',
        'subscription-billing.return_urls.cancel' => 'https://app.example.test/billing/cancel',
        'services.doku.client_id' => 'BRN-TEST',
        'services.doku.secret_key' => 'doku-test-secret',
        'services.doku.public_key' => 'unused-by-non-snap-checkout',
        'services.doku.base_url' => 'https://api-sandbox.doku.com',
        'services.doku.payment_due_minutes' => 60,
        'services.doku.notification_url' => 'https://api.example.test/api/central/billing/doku/webhook',
    ]);
});

afterEach(fn () => Mockery::close());

test('DOKU is bound as the subscription payment gateway', function () {
    expect(app(SubscriptionPaymentGateway::class))
        ->toBeInstanceOf(DokuSubscriptionPaymentGateway::class);
});

test('it creates a DOKU Checkout session using the gateway contract', function () {
    $client = Mockery::mock(DokuCheckoutClient::class);
    $client->shouldReceive('create')
        ->once()
        ->with(
            'BRN-TEST',
            'doku-test-secret',
            Mockery::on(fn (array $payload): bool => $payload['order']['invoice_number'] === 'SUB-INV-0001'
                && $payload['order']['amount'] === 199000
                && $payload['order']['line_items'][0]['price'] === 199000
                && $payload['customer']['email'] === 'owner@example.test'
                && $payload['order']['callback_url'] === 'https://app.example.test/billing/success'
                && $payload['additional_info']['override_notification_url'] === 'https://api.example.test/api/central/billing/doku/webhook'),
        )
        ->andReturn([
            'response' => [
                'order' => ['session_id' => 'doku-session-id'],
                'payment' => [
                    'token_id' => 'doku-token-id',
                    'url' => 'https://sandbox.doku.com/checkout-link',
                ],
            ],
        ]);
    app()->instance(DokuCheckoutClient::class, $client);

    $session = app(SubscriptionPaymentGateway::class)->createCheckout(
        orderId: 'SUB-INV-0001',
        grossAmount: 199000,
        customer: ['name' => 'Dipta', 'email' => 'owner@example.test'],
        items: [[
            'id' => 'starter',
            'name' => 'Sewantara Starter',
            'price' => 199000,
            'quantity' => 1,
        ]],
    );

    expect($session->token)->toBe('doku-session-id')
        ->and($session->redirectUrl)->toBe('https://sandbox.doku.com/checkout-link');
});

test('DOKU client signs the exact JSON body sent to Checkout', function () {
    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'response' => [
                'order' => ['session_id' => 'session-id'],
                'payment' => ['url' => 'https://sandbox.doku.com/checkout'],
            ],
        ]),
    ]);

    $payload = [
        'order' => ['amount' => 199000, 'invoice_number' => 'SUB-INV-SIGNED'],
        'payment' => ['payment_due_date' => 60],
    ];
    app(DokuCheckoutClient::class)->create('BRN-TEST', 'doku-test-secret', $payload);

    Http::assertSent(function ($request): bool {
        $signature = app(DokuSignature::class)->sign(
            'BRN-TEST',
            $request->header('Request-Id')[0],
            $request->header('Request-Timestamp')[0],
            '/checkout/v1/payment',
            $request->body(),
            'doku-test-secret',
        );

        return $request->url() === 'https://api-sandbox.doku.com/checkout/v1/payment'
            && $request->header('Client-Id')[0] === 'BRN-TEST'
            && $request->header('Signature')[0] === $signature;
    });
});

test('DOKU client normalizes rejected credentials', function () {
    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'error' => ['message' => 'Unauthorized'],
        ], 401),
    ]);

    expect(fn () => app(DokuCheckoutClient::class)->create(
        'BRN-TEST',
        'invalid-secret',
        ['order' => ['amount' => 10000]],
    ))->toThrow(SubscriptionGatewayAuthenticationFailed::class);
});

test('DOKU webhook verifies its signature and confirms a successful payment', function () {
    $confirm = Mockery::mock(ConfirmSubscriptionPayment::class);
    $confirm->shouldReceive('execute')
        ->once()
        ->withArgs(fn (
            string $paymentNumber,
            string $gateway,
            ?string $gatewayReference,
            ?string $gatewaySessionReference,
            array $metadata,
        ): bool => $paymentNumber === 'SUB-INV-WEBHOOK'
            && $gateway === 'doku'
            && $gatewayReference === 'original-request-id'
            && $gatewaySessionReference === null
            && $metadata['gross_amount'] === '199000'
            && $metadata['currency'] === 'IDR');
    app()->instance(ConfirmSubscriptionPayment::class, $confirm);

    $payload = [
        'order' => [
            'invoice_number' => 'SUB-INV-WEBHOOK',
            'amount' => 199000,
            'currency' => 'IDR',
        ],
        'transaction' => [
            'status' => 'SUCCESS',
            'original_request_id' => 'original-request-id',
        ],
    ];
    $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $requestId = 'notification-request-id';
    $timestamp = '2026-08-03T08:00:00Z';
    $signature = app(DokuSignature::class)->sign(
        'BRN-TEST',
        $requestId,
        $timestamp,
        '/api/central/billing/doku/webhook',
        $rawBody,
        'doku-test-secret',
    );

    $this->call(
        'POST',
        '/api/central/billing/doku/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => 'BRN-TEST',
            'HTTP_REQUEST_ID' => $requestId,
            'HTTP_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_SIGNATURE' => $signature,
        ],
        content: $rawBody,
    )->assertOk()->assertJsonPath('success', true);
});

test('DOKU webhook rejects a signature that does not match the raw body', function () {
    $this->withHeaders([
        'Client-Id' => 'BRN-TEST',
        'Request-Id' => 'invalid-request',
        'Request-Timestamp' => '2026-08-03T08:00:00Z',
        'Signature' => 'HMACSHA256=invalid',
    ])->postJson('/api/central/billing/doku/webhook', [
        'order' => ['invoice_number' => 'SUB-INV-TAMPERED', 'amount' => 199000],
        'transaction' => ['status' => 'SUCCESS'],
    ])->assertForbidden();
});
