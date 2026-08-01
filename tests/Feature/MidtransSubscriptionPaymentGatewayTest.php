<?php

use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSignatureVerifier;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSnapClient;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSubscriptionPaymentGateway;
use Midtrans\Config;

beforeEach(function () {
    config()->set([
        'subscription-billing.default' => 'midtrans',
        'services.midtrans.server_key' => 'SB-Mid-server-test',
        'services.midtrans.client_key' => 'SB-Mid-client-test',
        'services.midtrans.is_production' => false,
        'services.midtrans.is_3ds' => true,
    ]);
});

test('the Midtrans adapter is bound as the subscription payment gateway', function () {
    expect(app(SubscriptionPaymentGateway::class))
        ->toBeInstanceOf(MidtransSubscriptionPaymentGateway::class);
});

test('it creates a Snap checkout from the backend using the server key', function () {
    $snap = Mockery::mock(MidtransSnapClient::class);
    $snap->shouldReceive('createTransaction')
        ->once()
        ->with(Mockery::on(
            fn (array $payload): bool => $payload['transaction_details'] === [
                'order_id' => 'SUB-INV-0001',
                'gross_amount' => 199000,
            ],
        ))
        ->andReturn((object) [
            'token' => 'snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/redirect',
        ]);
    app()->instance(MidtransSnapClient::class, $snap);

    $session = app(SubscriptionPaymentGateway::class)->createCheckout(
        orderId: 'SUB-INV-0001',
        grossAmount: 199000,
        customer: [
            'first_name' => 'Dipta',
            'email' => 'owner@example.com',
        ],
        items: [[
            'id' => 'starter',
            'price' => 199000,
            'quantity' => 1,
            'name' => 'Sewantara Starter',
        ]],
    );

    expect($session->token)->toBe('snap-token')
        ->and($session->redirectUrl)->toBe('https://app.sandbox.midtrans.com/snap/redirect')
        ->and(Config::$serverKey)->toBe('SB-Mid-server-test')
        ->and(Config::$clientKey)->toBe('SB-Mid-client-test')
        ->and(Config::$isProduction)->toBeFalse()
        ->and(Config::$isSanitized)->toBeTrue()
        ->and(Config::$paymentIdempotencyKey)->toBe('SUB-INV-0001');
});

test('it verifies Midtrans webhook signatures without trusting the payload', function () {
    $payload = [
        'order_id' => 'SUB-INV-0001',
        'status_code' => '200',
        'gross_amount' => '199000.00',
    ];
    $payload['signature_key'] = hash(
        'sha512',
        $payload['order_id']
            .$payload['status_code']
            .$payload['gross_amount']
            .config('services.midtrans.server_key'),
    );

    $verifier = app(MidtransSignatureVerifier::class);

    expect($verifier->verify($payload))->toBeTrue()
        ->and($verifier->verify([
            ...$payload,
            'gross_amount' => '1.00',
        ]))->toBeFalse();
});

test('it rejects an invalid checkout before contacting Midtrans', function () {
    $snap = Mockery::mock(MidtransSnapClient::class);
    $snap->shouldNotReceive('createTransaction');
    app()->instance(MidtransSnapClient::class, $snap);

    expect(fn () => app(SubscriptionPaymentGateway::class)->createCheckout(
        orderId: 'invalid order id',
        grossAmount: 0,
        customer: [],
        items: [],
    ))->toThrow(InvalidArgumentException::class);
});
