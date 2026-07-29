<?php

use App\Models\SubscriptionPayment;
use App\Modules\SubscriptionBilling\Contracts\PaidTenantProvisioner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;

test('multi database provisioning uses the tenant migration directory', function () {
    expect(config('tenancy.bootstrappers'))
        ->toContain(DatabaseTenancyBootstrapper::class)
        ->and(config('tenancy.migration_parameters.--path'))
        ->toBe([database_path('migrations/tenant')]);
});

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    config()->set('services.midtrans.server_key', 'SB-Mid-server-test');

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
});

afterEach(function () {
    Schema::dropIfExists('subscription_payments');
    Mockery::close();
});

test('a verified paid notification automatically provisions the tenant', function () {
    SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-paid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-INV-PAID-1',
        'gateway' => 'midtrans',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'pending',
    ]);

    $provisioner = Mockery::mock(PaidTenantProvisioner::class);
    $provisioner->shouldReceive('provision')
        ->once()
        ->with('tenant-paid');
    app()->instance(PaidTenantProvisioner::class, $provisioner);

    $payload = paidMidtransPayload('SUB-INV-PAID-1');

    $this->postJson('/api/v1/billing/midtrans/webhook', $payload)
        ->assertOk()
        ->assertJsonPath('success', true);

    $payment = SubscriptionPayment::query()
        ->where('payment_number', 'SUB-INV-PAID-1')
        ->firstOrFail();

    expect($payment->status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->gateway_reference)->toBe('midtrans-transaction-1');
});

test('an invalid payment signature never provisions a tenant', function () {
    SubscriptionPayment::query()->create([
        'tenant_id' => 'tenant-unpaid',
        'plan_subscription_id' => 1,
        'payment_number' => 'SUB-INV-UNPAID-1',
        'gateway' => 'midtrans',
        'amount' => 199000,
        'currency' => 'IDR',
        'status' => 'pending',
    ]);

    $provisioner = Mockery::mock(PaidTenantProvisioner::class);
    $provisioner->shouldNotReceive('provision');
    app()->instance(PaidTenantProvisioner::class, $provisioner);

    $payload = paidMidtransPayload('SUB-INV-UNPAID-1');
    $payload['signature_key'] = 'invalid';

    $this->postJson('/api/v1/billing/midtrans/webhook', $payload)
        ->assertForbidden();

    expect(
        SubscriptionPayment::query()
            ->where('payment_number', 'SUB-INV-UNPAID-1')
            ->value('status'),
    )->toBe('pending');
});

test('a non-final Midtrans status does not provision a tenant', function () {
    $provisioner = Mockery::mock(PaidTenantProvisioner::class);
    $provisioner->shouldNotReceive('provision');
    app()->instance(PaidTenantProvisioner::class, $provisioner);

    $payload = paidMidtransPayload('SUB-INV-PENDING-1');
    $payload['transaction_status'] = 'pending';
    $payload['signature_key'] = midtransSignature($payload);

    $this->postJson('/api/v1/billing/midtrans/webhook', $payload)
        ->assertOk();
});

/**
 * @return array<string, string>
 */
function paidMidtransPayload(string $orderId): array
{
    $payload = [
        'order_id' => $orderId,
        'status_code' => '200',
        'gross_amount' => '199000.00',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'transaction_id' => 'midtrans-transaction-1',
    ];
    $payload['signature_key'] = midtransSignature($payload);

    return $payload;
}

/**
 * @param  array<string, string>  $payload
 */
function midtransSignature(array $payload): string
{
    return hash(
        'sha512',
        $payload['order_id']
            .$payload['status_code']
            .$payload['gross_amount']
            .config('services.midtrans.server_key'),
    );
}
