<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Modules\Payments\Application\CreateBookingPaymentCheckout;
use App\Modules\Payments\Application\HandlePaymentGatewayNotification;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\CheckoutRequest;
use App\Modules\Payments\Data\CheckoutSession;
use App\Modules\Payments\Data\GatewayNotification;
use App\Modules\Payments\Infrastructure\Midtrans\MidtransClient;
use App\Modules\Payments\Infrastructure\Midtrans\MidtransCredentialResolver;
use App\Modules\Payments\Infrastructure\Midtrans\MidtransCredentials;
use App\Modules\Payments\Infrastructure\Midtrans\MidtransPaymentGateway;
use App\Modules\Payments\PaymentGatewayManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Midtrans\Config;

beforeEach(function () {
    $this->originalDatabaseConnection = config('database.default');
});

afterEach(function () {
    DB::purge('payment_gateway_testing');
    config()->set('database.default', $this->originalDatabaseConnection);
    DB::setDefaultConnection($this->originalDatabaseConnection);
    config()->set('database.connections.payment_gateway_testing', null);
    Mockery::close();
});

test('Midtrans membuat checkout melalui kontrak payment gateway', function () {
    $client = Mockery::mock(MidtransClient::class);
    $client->shouldReceive('createTransaction')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => $payload['transaction_details'] === [
            'order_id' => 'PAY-BOOKING-1',
            'gross_amount' => 150000,
        ]))
        ->andReturn((object) [
            'token' => 'snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/redirect',
        ]);

    $credentials = Mockery::mock(MidtransCredentialResolver::class);
    $credentials->shouldReceive('resolve')->once()->andReturn(
        new MidtransCredentials('server-key', 'client-key', false, true),
    );

    $gateway = new MidtransPaymentGateway($client, $credentials);
    $checkout = $gateway->createCheckout(new CheckoutRequest(
        orderId: 'PAY-BOOKING-1',
        grossAmount: 150000,
        currency: 'IDR',
        customer: ['first_name' => 'Budi'],
        items: [[
            'id' => 'booking-1',
            'price' => 150000,
            'quantity' => 1,
            'name' => 'Pembayaran BK-1',
        ]],
        notificationUrl: 'https://api.example.com/webhook',
    ));

    expect($checkout)
        ->toBeInstanceOf(CheckoutSession::class)
        ->and($checkout->gateway)->toBe('midtrans')
        ->and($checkout->token)->toBe('snap-token')
        ->and(Config::$overrideNotifUrl)->toBe('https://api.example.com/webhook')
        ->and(Config::$paymentIdempotencyKey)->toBe('PAY-BOOKING-1');
});

test('Midtrans menormalisasi notifikasi yang tanda tangannya valid', function () {
    $credentials = Mockery::mock(MidtransCredentialResolver::class);
    $credentials->shouldReceive('resolve')->once()->andReturn(
        new MidtransCredentials('server-key', 'client-key', false, true),
    );
    $gateway = new MidtransPaymentGateway(
        Mockery::mock(MidtransClient::class),
        $credentials,
    );
    $payload = [
        'order_id' => 'PAY-BOOKING-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'midtrans-1',
    ];
    $payload['signature_key'] = hash(
        'sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'server-key',
    );

    $notification = $gateway->parseNotification($payload);

    expect($notification->status)->toBe('paid')
        ->and($notification->transactionId)->toBe('midtrans-1');
});

test('manager dapat ditambah driver baru tanpa mengubah application service', function () {
    $fake = new class implements PaymentGateway
    {
        public function code(): string
        {
            return 'fake';
        }

        public function createCheckout(CheckoutRequest $request): CheckoutSession
        {
            return new CheckoutSession('fake', 'token', 'https://example.com');
        }

        public function parseNotification(array $payload): GatewayNotification
        {
            return new GatewayNotification('order', null, '1.00', 'pending', $payload);
        }
    };
    app()->instance($fake::class, $fake);
    $manager = new PaymentGatewayManager(app(), ['fake' => $fake::class], 'fake');

    expect($manager->driver())->toBe($fake);
});

test('notifikasi pembayaran diproses secara idempoten', function () {
    configurePaymentGatewayDatabase();
    createPaymentGatewayTables();

    DB::table('bookings')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'total_amount' => 200000,
        'paid_amount' => 0,
        'remaining_amount' => 200000,
        'payment_status' => 'unpaid',
    ]);
    DB::table('payments')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'booking_id' => 1,
        'payment_number' => 'PAY-IDEMPOTENT',
        'type' => 'down_payment',
        'method' => 'payment_gateway',
        'amount' => 50000,
        'status' => 'pending',
        'gateway' => 'fake',
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('code')->andReturn('fake');
    $gateway->shouldReceive('parseNotification')->twice()->andReturn(
        new GatewayNotification(
            'PAY-IDEMPOTENT',
            'transaction-1',
            '50000.00',
            'paid',
            ['event' => 'paid'],
        ),
    );
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('driver')->twice()->with('fake')->andReturn($gateway);
    $handler = new HandlePaymentGatewayNotification($manager);

    $handler->execute('fake', []);
    $handler->execute('fake', []);

    expect(Payment::query()->findOrFail(1)->status)->toBe('paid')
        ->and(DB::table('bookings')->where('id', 1)->value('paid_amount'))->toBe(50000)
        ->and(DB::table('payment_transactions')->count())->toBe(1);
});

test('checkout aktif mencadangkan sisa tagihan agar tidak terjadi checkout berlebih', function () {
    configurePaymentGatewayDatabase();
    createPaymentGatewayTables();

    DB::table('customers')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'name' => 'Budi',
        'email' => 'budi@example.com',
    ]);
    DB::table('tenant_payment_methods')->insert([
        'tenant_id' => 'tenant-a',
        'method' => 'fake',
        'is_enabled' => true,
    ]);
    DB::table('bookings')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'customer_id' => 1,
        'booking_number' => 'BOOK-1',
        'total_amount' => 200000,
        'paid_amount' => 0,
        'remaining_amount' => 200000,
        'payment_status' => 'unpaid',
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('code')->andReturn('fake');
    $gateway->shouldReceive('createCheckout')->once()->andReturn(
        new CheckoutSession('fake', 'checkout-token', 'https://example.com/pay'),
    );
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('driver')->twice()->with('fake')->andReturn($gateway);
    $service = new CreateBookingPaymentCheckout($manager);
    $booking = Booking::query()->findOrFail(1);

    $result = $service->execute(
        'tenant-a',
        $booking,
        'down_payment',
        150000,
        'fake',
        'https://api.example.com/webhook',
        null,
    );

    expect($result->payment->status)->toBe('pending')
        ->and($result->checkout->token)->toBe('checkout-token');

    expect(fn () => $service->execute(
        'tenant-a',
        $booking,
        'down_payment',
        100000,
        'fake',
        'https://api.example.com/webhook',
        null,
    ))->toThrow(ValidationException::class);
});

function configurePaymentGatewayDatabase(): void
{
    config()->set('database.connections.payment_gateway_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('database.default', 'payment_gateway_testing');
    DB::purge('payment_gateway_testing');
    DB::setDefaultConnection('payment_gateway_testing');
}

function createPaymentGatewayTables(): void
{
    Schema::create('customers', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('tenant_payment_methods', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('method');
        $table->boolean('is_enabled');
        $table->text('configuration')->nullable();
        $table->timestamps();
    });
    Schema::create('bookings', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->string('booking_number')->nullable();
        $table->decimal('total_amount', 18, 2);
        $table->decimal('paid_amount', 18, 2);
        $table->decimal('remaining_amount', 18, 2);
        $table->string('payment_status');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('payments', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('booking_id');
        $table->string('payment_number');
        $table->string('type');
        $table->string('method');
        $table->decimal('amount', 18, 2);
        $table->string('status');
        $table->string('gateway')->nullable();
        $table->string('gateway_reference')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });
    Schema::create('payment_transactions', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('payment_id');
        $table->string('gateway');
        $table->string('transaction_id')->nullable();
        $table->json('request_payload')->nullable();
        $table->json('response_payload')->nullable();
        $table->json('callback_payload')->nullable();
        $table->boolean('signature_valid')->nullable();
        $table->timestamps();
    });
}
