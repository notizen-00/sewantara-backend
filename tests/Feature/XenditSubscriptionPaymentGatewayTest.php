<?php

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Modules\SubscriptionBilling\Application\ActivateSubscriptionForPaidPayment;
use App\Modules\SubscriptionBilling\Application\Exceptions\SubscriptionGatewayAuthenticationFailed;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use App\Modules\SubscriptionBilling\Events\SubscriptionPaymentPaid;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditPaymentSessionClient;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditSubscriptionPaymentGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
    Schema::dropIfExists(config('laravel-subscriptions.tables.subscription_usage'));
    Schema::dropIfExists(config('laravel-subscriptions.tables.subscriptions'));
    Schema::dropIfExists(config('laravel-subscriptions.tables.plans'));
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

test('it normalizes an invalid Xendit API key without exposing the gateway response', function () {
    Http::fake([
        'https://api.xendit.co/sessions' => Http::response([
            'message' => 'The API key provided is invalid.',
        ], 401),
    ]);

    expect(fn () => app(XenditPaymentSessionClient::class)->create(
        'xnd_development_invalid',
        ['reference_id' => 'SUB-INVALID-KEY'],
    ))->toThrow(
        SubscriptionGatewayAuthenticationFailed::class,
        'Autentikasi payment gateway subscription gagal.',
    );
});

test('the public Xendit webhook confirms the matching subscription payment', function () {
    createXenditSubscriptionPaymentsTable();
    Event::fake([SubscriptionPaymentPaid::class]);
    SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-paid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-INV-XENDIT-1',
        'gateway' => 'xendit',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'pending',
        'gateway_reference' => 'ps-661f87c614802d6c402cd82d',
        'metadata' => [
            'checkout' => ['token' => 'ps-661f87c614802d6c402cd82d'],
        ],
    ]);

    $this->withHeader('x-callback-token', 'xendit-webhook-token')
        ->postJson('/api/central/billing/xendit/webhook', [
            'event' => 'payment_session.completed',
            'data' => [
                'reference_id' => 'SUB-INV-XENDIT-1',
                'payment_session_id' => 'ps-661f87c614802d6c402cd82d',
                'payment_id' => 'py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c',
                'amount' => 199000,
                'currency' => 'IDR',
                'session_type' => 'PAY',
                'status' => 'COMPLETED',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $payment = SubscriptionPayment::query()->firstOrFail();
    expect($payment->status)->toBe('paid')
        ->and($payment->tenant_id)->toBe('tenant-paid')
        ->and($payment->gateway_reference)->toBe('py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c');

    $subscription = DB::table(config('laravel-subscriptions.tables.subscriptions'))
        ->where('id', 1)
        ->first();

    expect($subscription->trial_ends_at)->toBeNull()
        ->and($subscription->canceled_at)->toBeNull()
        ->and($subscription->ends_at)->not->toBeNull()
        ->and(now()->isBefore($subscription->ends_at))->toBeTrue();

    $endsAt = $subscription->ends_at;

    $this->withHeader('x-callback-token', 'xendit-webhook-token')
        ->postJson('/api/central/billing/xendit/webhook', [
            'event' => 'payment_session.completed',
            'data' => [
                'reference_id' => 'SUB-INV-XENDIT-1',
                'payment_session_id' => 'ps-661f87c614802d6c402cd82d',
                'payment_id' => 'py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c',
                'amount' => 199000,
                'currency' => 'IDR',
                'session_type' => 'PAY',
                'status' => 'COMPLETED',
            ],
        ])
        ->assertOk();

    expect(
        DB::table(config('laravel-subscriptions.tables.subscriptions'))
            ->where('id', 1)
            ->value('ends_at'),
    )->toBe($endsAt);

    $this->withHeader('x-callback-token', 'xendit-webhook-token')
        ->postJson('/api/central/billing/xendit/webhook', [
            'event' => 'payment_session.expired',
            'data' => [
                'reference_id' => 'SUB-INV-XENDIT-1',
                'payment_session_id' => 'ps-661f87c614802d6c402cd82d',
                'amount' => 199000,
                'currency' => 'IDR',
                'session_type' => 'PAY',
                'status' => 'EXPIRED',
            ],
        ])
        ->assertOk();

    expect(SubscriptionPayment::query()->sole()->status)->toBe('paid');
    Event::assertDispatchedTimes(SubscriptionPaymentPaid::class, 1);
});

test('a paid checkout converts a trial into an active subscription', function () {
    createXenditSubscriptionPaymentsTable();
    $originalEnd = now()->addMonth()->startOfSecond();
    DB::table(config('laravel-subscriptions.tables.subscriptions'))
        ->where('id', 1)
        ->update([
            'trial_ends_at' => now()->addDays(14),
            'starts_at' => now()->addDays(14),
            'ends_at' => $originalEnd,
            'canceled_at' => null,
        ]);
    $payment = SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-paid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-TRIAL-ACTIVATION',
        'gateway' => 'xendit',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'paid',
    ]);

    $subscription = app(ActivateSubscriptionForPaidPayment::class)->execute($payment);

    expect($subscription->onTrial())->toBeFalse()
        ->and($subscription->active())->toBeTrue()
        ->and($subscription->trial_ends_at)->toBeNull()
        ->and($subscription->starts_at->isToday())->toBeTrue()
        ->and($subscription->ends_at->equalTo($originalEnd))->toBeTrue();
});

test('a paid checkout extends an active subscription from its current end date', function () {
    createXenditSubscriptionPaymentsTable();
    $originalEnd = now()->addDays(10)->startOfSecond();
    DB::table(config('laravel-subscriptions.tables.subscriptions'))
        ->where('id', 1)
        ->update([
            'trial_ends_at' => null,
            'starts_at' => now()->subDays(20),
            'ends_at' => $originalEnd,
            'canceled_at' => null,
        ]);
    $payment = SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-paid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-ACTIVE-EXTENSION',
        'gateway' => 'xendit',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'paid',
    ]);

    $subscription = app(ActivateSubscriptionForPaidPayment::class)->execute($payment);

    expect($subscription->active())->toBeTrue()
        ->and($subscription->ends_at->equalTo($originalEnd->copy()->addMonth()))->toBeTrue();
});

test('the Xendit expired webhook expires only a pending payment', function () {
    createXenditSubscriptionPaymentsTable();
    SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-paid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-INV-XENDIT-EXPIRED',
        'gateway' => 'xendit',
        'gateway_reference' => 'ps-expired-session-000000001',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'pending',
    ]);

    $this->withHeader('x-callback-token', 'xendit-webhook-token')
        ->postJson('/api/central/billing/xendit/webhook', [
            'event' => 'payment_session.expired',
            'data' => [
                'reference_id' => 'SUB-INV-XENDIT-EXPIRED',
                'payment_session_id' => 'ps-expired-session-000000001',
                'amount' => 199000,
                'currency' => 'IDR',
                'session_type' => 'PAY',
                'status' => 'EXPIRED',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Pembayaran langganan kedaluwarsa.');

    expect(SubscriptionPayment::query()->sole()->status)->toBe('expired');
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

    Schema::create(config('laravel-subscriptions.tables.plans'), function (Blueprint $table): void {
        $table->id();
        $table->json('name');
        $table->string('slug')->unique();
        $table->json('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->decimal('price', 18, 2);
        $table->decimal('signup_fee', 18, 2)->default(0);
        $table->char('currency', 3);
        $table->unsignedSmallInteger('trial_period')->default(0);
        $table->string('trial_interval')->default('day');
        $table->unsignedSmallInteger('invoice_period')->default(1);
        $table->string('invoice_interval')->default('month');
        $table->unsignedSmallInteger('grace_period')->default(0);
        $table->string('grace_interval')->default('day');
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create(config('laravel-subscriptions.tables.subscriptions'), function (Blueprint $table): void {
        $table->id();
        $table->string('subscriber_type');
        $table->string('subscriber_id');
        $table->unsignedBigInteger('plan_id');
        $table->json('name');
        $table->string('slug');
        $table->json('description')->nullable();
        $table->timestamp('trial_ends_at')->nullable();
        $table->timestamp('starts_at')->nullable();
        $table->timestamp('ends_at')->nullable();
        $table->timestamp('canceled_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create(config('laravel-subscriptions.tables.subscription_usage'), function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('subscription_id');
        $table->timestamps();
        $table->softDeletes();
    });

    DB::table(config('laravel-subscriptions.tables.plans'))->insert([
        'id' => 1,
        'name' => json_encode(['id' => 'Pemula', 'en' => 'Starter']),
        'slug' => 'starter',
        'is_active' => true,
        'price' => 199000,
        'signup_fee' => 0,
        'currency' => 'IDR',
        'trial_period' => 14,
        'trial_interval' => 'day',
        'invoice_period' => 1,
        'invoice_interval' => 'month',
        'grace_period' => 3,
        'grace_interval' => 'day',
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table(config('laravel-subscriptions.tables.subscriptions'))->insert([
        'id' => 1,
        'subscriber_type' => Tenant::class,
        'subscriber_id' => 'tenant-paid',
        'plan_id' => 1,
        'name' => json_encode(['id' => 'Utama', 'en' => 'Main']),
        'slug' => 'main',
        'trial_ends_at' => now()->subMonth(),
        'starts_at' => now()->subMonths(2),
        'ends_at' => now()->subMonth(),
        'canceled_at' => now()->subMonth(),
        'created_at' => now()->subMonths(2),
        'updated_at' => now()->subMonth(),
    ]);

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
