<?php

use App\Models\SubscriptionPayment;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditPaymentSessionClient;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditSubscriptionPaymentGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'tenancy.database.central_connection' => 'sqlite',
        'subscription-billing.default' => 'xendit',
        'subscription-billing.return_urls.success' => 'https://dashboard.example.test/billing/success',
        'subscription-billing.return_urls.cancel' => 'https://dashboard.example.test/billing/cancel',
        'services.xendit.secret_key' => 'xnd_development_secret',
        'services.xendit.public_key' => 'xnd_public_development',
        'services.xendit.webhook_token' => 'xendit-webhook-token',
        'services.xendit.base_url' => 'https://api.xendit.co',
    ]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
});

afterEach(function () {
    Schema::dropIfExists('subscription_payments');
    Mockery::close();
});

test('Xendit is bound as the default subscription payment gateway', function () {
    expect(app(SubscriptionPaymentGateway::class))
        ->toBeInstanceOf(XenditSubscriptionPaymentGateway::class);
});

test('it creates a Xendit Payment Session for a subscription invoice', function () {
    $client = Mockery::mock(XenditPaymentSessionClient::class);
    $client->shouldReceive('create')
        ->once()
        ->with(
            'xnd_development_secret',
            Mockery::on(fn (array $payload): bool => $payload['reference_id'] === 'SUB-INV-0001'
                && $payload['session_type'] === 'PAY'
                && $payload['mode'] === 'PAYMENT_LINK'
                && $payload['amount'] === 199000
                && $payload['items'][0]['type'] === 'DIGITAL_SERVICE'
                && $payload['customer']['email'] === 'owner@example.com'
                && $payload['success_return_url'] === 'https://dashboard.example.test/billing/success'
                && $payload['cancel_return_url'] === 'https://dashboard.example.test/billing/cancel'),
        )
        ->andReturn([
            'payment_session_id' => 'ps-661f87c614802d6c402cd82d',
            'payment_link_url' => 'https://dev.xen.to/subscription-test',
        ]);
    app()->instance(XenditPaymentSessionClient::class, $client);

    $session = app(SubscriptionPaymentGateway::class)->createCheckout(
        orderId: 'SUB-INV-0001',
        grossAmount: 199000,
        customer: ['first_name' => 'Dipta', 'email' => 'owner@example.com'],
        items: [[
            'id' => 'starter',
            'price' => 199000,
            'quantity' => 1,
            'name' => 'Sewantara Starter',
        ]],
    );

    expect($session->token)->toBe('ps-661f87c614802d6c402cd82d')
        ->and($session->redirectUrl)->toBe('https://dev.xen.to/subscription-test');
});

test('the public Xendit webhook confirms the matching subscription payment', function () {
    createXenditSubscriptionPaymentsTable();
    SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-paid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-INV-XENDIT-1',
        'gateway' => 'xendit',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'pending',
    ]);

    $this->withHeader('x-callback-token', 'xendit-webhook-token')
        ->postJson('/api/central/billing/xendit/webhook', [
            'event' => 'payment_session.completed',
            'data' => [
                'reference_id' => 'SUB-INV-XENDIT-1',
                'payment_session_id' => 'ps-661f87c614802d6c402cd82d',
                'payment_id' => 'py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c',
                'amount' => 199000,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $payment = SubscriptionPayment::query()->firstOrFail();
    expect($payment->status)->toBe('paid')
        ->and($payment->tenant_id)->toBe('tenant-paid')
        ->and($payment->gateway_reference)->toBe('py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c');
});

test('the public Xendit webhook rejects an invalid callback token', function () {
    $this->withHeader('x-callback-token', 'invalid-token')
        ->postJson('/api/central/billing/xendit/webhook', [
            'event' => 'payment_session.completed',
            'data' => [],
        ])
        ->assertForbidden();
});

function createXenditSubscriptionPaymentsTable(): void
{
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('subscription_payments', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('plan_subscription_id');
        $table->string('payment_number')->unique();
        $table->string('gateway')->nullable();
        $table->string('gateway_reference')->nullable();
        $table->decimal('amount', 18, 2);
        $table->char('currency', 3);
        $table->string('status');
        $table->timestamp('paid_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
}
