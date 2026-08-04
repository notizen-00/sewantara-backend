<?php

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Modules\SubscriptionBilling\Application\CreateSubscriptionCheckout;
use App\Modules\SubscriptionBilling\Application\Data\CheckoutSession;
use App\Modules\SubscriptionBilling\Application\GetSubscriptionPayment;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravelcm\Subscriptions\Models\Plan;
use Laravelcm\Subscriptions\Models\Subscription;

beforeEach(function () {
    config()->set([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'tenancy.database.central_connection' => 'sqlite',
        'subscription-billing.default' => 'xendit',
    ]);
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

    Schema::create('engines', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->decimal('monthly_price', 18, 2)->default(0);
        $table->boolean('is_core')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('tenant_engines', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('engine_id');
        $table->boolean('is_enabled')->default(true);
        $table->timestamp('enabled_at')->nullable();
        $table->timestamp('disabled_at')->nullable();
        $table->decimal('price_snapshot', 18, 2)->default(0);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('subscription_payments');
    Schema::dropIfExists('tenant_engines');
    Schema::dropIfExists('engines');
    Mockery::close();
});

test('it creates a pending Xendit checkout using the current plan price', function () {
    [$tenant, $subscription] = subscriptionCheckoutTenant();
    $gateway = Mockery::mock(SubscriptionPaymentGateway::class);
    $gateway->shouldReceive('createCheckout')
        ->once()
        ->withArgs(fn (
            string $orderId,
            int $grossAmount,
            array $customer,
            array $items,
        ): bool => str_starts_with($orderId, 'SUB-')
            && $grossAmount === 199000
            && $customer['email'] === 'owner@example.test'
            && $items[0]['id'] === 'starter'
            && $items[0]['price'] === 199000)
        ->andReturn(new CheckoutSession(
            'ps-661f87c614802d6c402cd82d',
            'https://dev.xen.to/subscription-test',
        ));

    $result = (new CreateSubscriptionCheckout($gateway))->execute($tenant, [
        'name' => 'Owner Rental',
        'email' => 'owner@example.test',
    ]);

    expect($result['payment']->plan_subscription_id)->toBe($subscription->getKey())
        ->and($result['payment']->status)->toBe('pending')
        ->and($result['payment']->gateway)->toBe('xendit')
        ->and($result['payment']->amount)->toBe('199000.00')
        ->and($result['payment']->gateway_reference)->toBe('ps-661f87c614802d6c402cd82d')
        ->and($result['payment']->metadata['checkout']['redirect_url'])
        ->toBe('https://dev.xen.to/subscription-test')
        ->and(SubscriptionPayment::query()->count())->toBe(1);
});

test('it marks the payment failed when Xendit cannot create a session', function () {
    [$tenant] = subscriptionCheckoutTenant();
    $gateway = Mockery::mock(SubscriptionPaymentGateway::class);
    $gateway->shouldReceive('createCheckout')
        ->once()
        ->andThrow(new RuntimeException('Xendit unavailable'));

    expect(fn () => (new CreateSubscriptionCheckout($gateway))->execute($tenant, []))
        ->toThrow(RuntimeException::class, 'Xendit unavailable');

    expect(SubscriptionPayment::query()->sole()->status)->toBe('failed');
});

test('it only returns a subscription payment owned by the current tenant', function () {
    $payment = SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-other',
        'plan_subscription_id' => 10,
        'payment_number' => 'SUB-OTHER-TENANT',
        'gateway' => 'xendit',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'pending',
    ]);
    $tenant = new Tenant;
    $tenant->forceFill(['id' => 'tenant-checkout']);

    expect(fn () => (new GetSubscriptionPayment)->execute(
        $tenant,
        (string) $payment->getKey(),
    ))->toThrow(ModelNotFoundException::class);
});

/** @return array{Tenant, Subscription} */
function subscriptionCheckoutTenant(): array
{
    $plan = new Plan([
        'slug' => 'starter',
        'name' => ['id' => 'Pemula', 'en' => 'Starter'],
        'description' => ['id' => 'Paket awal', 'en' => 'Starter plan'],
        'is_active' => true,
        'price' => 199000,
        'currency' => 'IDR',
        'invoice_period' => 1,
        'invoice_interval' => 'month',
    ]);
    $plan->forceFill(['id' => 1]);

    $subscription = new Subscription;
    $subscription->forceFill(['id' => 10, 'plan_id' => 1]);
    $subscription->setRelation('plan', $plan);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => 'tenant-checkout']);
    $tenant->shouldReceive('planSubscription')
        ->with('main')
        ->andReturn($subscription);

    return [$tenant, $subscription];
}
