<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\IdempotencyRecord;
use App\Models\PublicQuote;
use App\Modules\Bookings\Application\ManageBookings;
use App\Modules\Inventory\Application\InventoryBookingLifecycle;
use App\Modules\PublicApi\Application\CreatePublicBooking;
use App\Modules\PublicApi\Application\CreatePublicQuote;
use App\Modules\PublicApi\Application\ExpirePublicStockHolds;
use App\Modules\PublicApi\Application\PublicAvailability;
use App\Modules\PublicApi\Application\PublicIdempotency;
use App\Modules\PublicApi\Application\PublicPayments;
use App\Modules\PublicApi\Application\TrackPublicBooking;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Modules\PublicApi\Support\CanonicalPayload;
use App\Modules\PublicApi\Support\TrackingToken;
use App\Modules\RentalEngine\Application\RentalEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('database.connections.public_transaction_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    config()->set('database.default', 'public_transaction_testing');
    config()->set('cache.default', 'array');
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    DB::purge('public_transaction_testing');
    DB::setDefaultConnection('public_transaction_testing');
    Cache::store('array')->flush();
    createPublicTransactionTables();
    seedPublicTransactionProduct();
});

afterEach(function () {
    DB::purge('public_transaction_testing');
    Mockery::close();
});

test('public quote is calculated by the rental engine with an immutable integer snapshot', function () {
    $action = new CreatePublicQuote(
        app(RentalEngine::class),
        new PublicAvailability,
    );
    $startsAt = now()->addDay()->startOfHour();
    $quote = $action->execute('tenant-a', [
        'product_id' => DB::table('products')->value('public_id'),
        'variant_id' => null,
        'booking' => [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addDays(2)->toIso8601String(),
            'quantity' => 2,
        ],
        'addons' => [],
        'coupon_code' => null,
    ]);

    expect($quote)
        ->subtotal->toBe(400000)
        ->deposit->toBe(200000)
        ->grand_total->toBe(600000)
        ->payable_now->toBe(600000)
        ->currency->toBe('IDR');

    $stored = PublicQuote::query()->sole();

    expect($stored->request_hash)
        ->toBe(CanonicalPayload::hash($stored->request_snapshot))
        ->and($stored->pricing_snapshot['unit_amount'])->toBe(100000)
        ->and($stored->pricing_snapshot['duration'])->toBe(2);
});

test('public quote rejects unsupported variants and requires an explicit primary branch', function () {
    $action = new CreatePublicQuote(
        app(RentalEngine::class),
        new PublicAvailability,
    );
    $startsAt = now()->addDay()->startOfHour();
    $payload = [
        'product_id' => DB::table('products')->value('public_id'),
        'variant_id' => (string) Str::uuid(),
        'booking' => [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addDay()->toIso8601String(),
            'quantity' => 1,
        ],
        'addons' => [],
        'coupon_code' => null,
    ];

    try {
        $action->execute('tenant-a', $payload);
        $this->fail('Expected an unsupported variant error.');
    } catch (PublicApiException $exception) {
        expect($exception->errorCode)->toBe('PRODUCT_UNAVAILABLE');
    }

    $payload['variant_id'] = null;
    DB::table('branches')->update(['is_primary' => false]);

    try {
        $action->execute('tenant-a', $payload);
        $this->fail('Expected a missing primary branch error.');
    } catch (PublicApiException $exception) {
        expect($exception->errorCode)->toBe('PRODUCT_UNAVAILABLE');
    }
});

test('idempotency replays the first result and rejects a changed payload', function () {
    $service = new PublicIdempotency;
    $calls = 0;
    $create = function (IdempotencyRecord $record) use (&$calls): array {
        $calls++;

        return ['data' => ['value' => 'first'], 'status' => 201];
    };
    $resume = fn (IdempotencyRecord $record): array => [
        'data' => ['value' => 'resumed'],
        'status' => 201,
    ];
    $first = $service->execute(
        'tenant-a',
        'POST:/bookings',
        (string) Str::uuid(),
        ['quote_id' => 'quote-a'],
        $create,
        $resume,
    );
    $key = IdempotencyRecord::query()->sole()->idempotency_key;
    $second = $service->execute(
        'tenant-a',
        'POST:/bookings',
        $key,
        ['quote_id' => 'quote-a'],
        $create,
        $resume,
    );

    expect($calls)->toBe(1)
        ->and($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeTrue()
        ->and($second->data)->toBe(['value' => 'first']);

    try {
        $service->execute(
            'tenant-a',
            'POST:/bookings',
            $key,
            ['quote_id' => 'quote-b'],
            $create,
            $resume,
        );
        $this->fail('Expected an idempotency conflict.');
    } catch (PublicApiException $exception) {
        expect($exception->errorCode)->toBe('IDEMPOTENCY_CONFLICT');
    }
});

test('booking replay creates one booking and stores only the tracking token hash', function () {
    $quoteAction = new CreatePublicQuote(
        app(RentalEngine::class),
        new PublicAvailability,
    );
    $startsAt = now()->addDay()->startOfHour();
    $quotePayload = $quoteAction->execute('tenant-a', [
        'product_id' => DB::table('products')->value('public_id'),
        'variant_id' => null,
        'booking' => [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addDays(2)->toIso8601String(),
            'quantity' => 2,
        ],
        'addons' => [],
        'coupon_code' => null,
    ]);
    $quote = PublicQuote::query()->findOrFail($quotePayload['quote_id']);
    $bookings = Mockery::mock(ManageBookings::class);
    $bookings->shouldReceive('create')->once()->andReturnUsing(
        function (string $tenantId, ?int $actorId, array $attributes) use ($quote): Booking {
            $booking = Booking::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $attributes['branch_id'],
                'customer_id' => $attributes['customer_id'],
                'booking_number' => 'SWJ-TEST-001',
                'start_at' => $attributes['start_at'],
                'end_at' => $attributes['end_at'],
                'status' => 'pending',
                'booking_channel' => 'online',
                'fulfillment_type' => 'pickup',
                'subtotal' => $quote->pricing_snapshot['subtotal'],
                'deposit_amount' => $quote->pricing_snapshot['deposit'],
                'total_amount' => $quote->pricing_snapshot['subtotal'],
                'paid_amount' => 0,
                'remaining_amount' => $quote->pricing_snapshot['subtotal'],
                'payment_status' => 'unpaid',
            ]);
            BookingItem::query()->create([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->getKey(),
                'product_id' => $quote->product_id,
                'product_name' => 'Kamera Test',
                'inventory_type' => 'quantity',
                'pricing_type' => 'daily',
                'quantity' => 2,
                'duration' => 2,
                'unit_price' => 100000,
                'subtotal' => 400000,
                'deposit_amount' => 200000,
                'total_amount' => 400000,
            ]);
            DB::table('invoices')->insert([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->getKey(),
                'total_amount' => 400000,
                'remaining_amount' => 400000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $booking->load('items');
        },
    );
    $availability = Mockery::mock(PublicAvailability::class);
    $availability->shouldReceive('assertAvailable')->once();
    $availability->shouldReceive('lockSerializedUnits')->once()->andReturn([]);
    $payments = Mockery::mock(PublicPayments::class);
    $payments->shouldReceive('assertMethodAvailable')->once()->andReturn('midtrans');
    $payments->shouldReceive('createForBooking')->once()->andReturn([
        'public_id' => (string) Str::uuid(),
        'method' => 'qris',
        'gateway' => 'midtrans',
        'status' => 'pending',
        'amount' => 600000,
        'currency' => 'IDR',
        'redirect_url' => 'https://payments.example.test/checkout',
    ]);
    $action = new CreatePublicBooking(
        $bookings,
        $availability,
        new PublicIdempotency,
        $payments,
    );
    $key = (string) Str::uuid();
    $attributes = [
        'quote_id' => $quote->getKey(),
        'customer' => [
            'name' => 'Budi Test',
            'phone' => '+628123456789',
            'email' => 'budi@example.test',
        ],
        'notes' => null,
        'agreement' => [
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ],
        'payment_method' => 'qris',
    ];
    $first = $action->execute('tenant-a', $key, $attributes);
    $second = $action->execute('tenant-a', $key, $attributes);
    $booking = Booking::query()->sole();

    expect($first->data['tracking']['token'])
        ->toBe($second->data['tracking']['token'])
        ->and(TrackingToken::digest($first->data['tracking']['token']))
        ->toBe($booking->tracking_token_hash)
        ->and(DB::table('bookings')->count())->toBe(1)
        ->and(DB::table('stock_holds')->count())->toBe(1)
        ->and(DB::table('deposits')->value('amount'))->toBe(200000)
        ->and((int) DB::table('bookings')->value('total_amount'))->toBe(600000)
        ->and((int) DB::table('invoices')->value('total_amount'))->toBe(600000)
        ->and(json_encode(IdempotencyRecord::query()->sole()->response_body))
        ->not->toContain($first->data['tracking']['token']);
});

test('expired stock hold releases a reservation exactly once', function () {
    $booking = Booking::query()->create([
        'tenant_id' => 'tenant-a',
        'branch_id' => DB::table('branches')->value('id'),
        'customer_id' => DB::table('customers')->insertGetId([
            'tenant_id' => 'tenant-a',
            'name' => 'Budi',
            'phone' => '+628123456789',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'booking_number' => 'SWJ-EXPIRE-001',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => 'pending',
        'booking_channel' => 'online',
        'fulfillment_type' => 'pickup',
        'total_amount' => 100000,
        'remaining_amount' => 100000,
        'payment_status' => 'unpaid',
    ]);
    DB::table('stock_holds')->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => 'tenant-a',
        'booking_id' => $booking->getKey(),
        'product_id' => DB::table('products')->value('id'),
        'branch_id' => $booking->branch_id,
        'starts_at' => $booking->start_at,
        'ends_at' => $booking->end_at,
        'quantity' => 1,
        'expires_at' => now()->subMinute(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('payments')->insert([
        'tenant_id' => 'tenant-a',
        'booking_id' => $booking->getKey(),
        'payment_number' => 'PAY-EXPIRE-001',
        'type' => 'full_payment',
        'method' => 'payment_gateway',
        'amount' => 100000,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $inventory = Mockery::mock(InventoryBookingLifecycle::class);
    $inventory->shouldReceive('releaseReservation')->once();
    $action = new ExpirePublicStockHolds($inventory);

    expect($action->execute())->toBe(1)
        ->and($action->execute())->toBe(0)
        ->and(DB::table('stock_holds')->value('status'))->toBe('expired')
        ->and(DB::table('bookings')->value('status'))->toBe('expired')
        ->and(DB::table('payments')->value('status'))->toBe('expired')
        ->and(DB::table('booking_status_histories')->count())->toBe(1);
});

test('tracking requires both the hashed token and normalized verifier and masks contact data', function () {
    $customerId = DB::table('customers')->insertGetId([
        'tenant_id' => 'tenant-a',
        'name' => 'Budi Santoso',
        'phone' => '+628123456789',
        'email' => 'budi@example.test',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $token = 'tracking-token-which-is-long-enough-for-the-public-api';
    $booking = Booking::query()->create([
        'tenant_id' => 'tenant-a',
        'branch_id' => DB::table('branches')->value('id'),
        'customer_id' => $customerId,
        'booking_number' => 'SWJ-TRACK-001',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => 'pending',
        'booking_channel' => 'online',
        'fulfillment_type' => 'pickup',
        'subtotal' => 100000,
        'deposit_amount' => 50000,
        'total_amount' => 150000,
        'remaining_amount' => 150000,
        'payment_status' => 'unpaid',
        'tracking_token_hash' => TrackingToken::digest($token),
    ]);
    BookingItem::query()->create([
        'tenant_id' => 'tenant-a',
        'booking_id' => $booking->getKey(),
        'product_id' => DB::table('products')->value('id'),
        'product_name' => 'Kamera Test',
        'inventory_type' => 'quantity',
        'pricing_type' => 'daily',
        'quantity' => 1,
        'duration' => 1,
        'unit_price' => 100000,
        'subtotal' => 100000,
        'deposit_amount' => 50000,
        'total_amount' => 100000,
    ]);
    $tracking = new TrackPublicBooking;
    $result = $tracking->execute(
        'SWJ-TRACK-001',
        ['type' => 'phone', 'value' => '+628123456789'],
        $token,
    );

    expect($result['customer']['name'])->not->toBe('Budi Santoso')
        ->and($result['customer']['phone'])->not->toBe('+628123456789')
        ->and($result['customer']['email'])->toBe('b***@example.test');

    try {
        $tracking->execute(
            'SWJ-TRACK-001',
            ['type' => 'phone', 'value' => '+628000000000'],
            $token,
        );
        $this->fail('Expected tracking verification to fail.');
    } catch (PublicApiException $exception) {
        expect($exception->errorCode)->toBe('TRACKING_VERIFICATION_FAILED');
    }
});

function createPublicTransactionTables(): void
{
    Schema::create('tenant_business_profiles', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('business_name');
        $table->char('currency', 3);
        $table->timestamps();
    });
    Schema::create('rental_configurations', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('rental_model');
        $table->string('booking_strategy');
        $table->string('allocation_strategy');
        $table->unsignedInteger('slot_duration_minutes')->nullable();
        $table->boolean('allow_walk_in');
        $table->boolean('allow_online_booking');
        $table->boolean('realtime_availability');
        $table->timestamps();
    });
    Schema::create('branches', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('code');
        $table->boolean('is_active');
        $table->boolean('is_public');
        $table->boolean('is_primary');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->uuid('public_id');
        $table->string('name');
        $table->string('slug');
        $table->string('inventory_type');
        $table->string('default_pricing_type');
        $table->decimal('deposit_amount', 18, 2);
        $table->boolean('is_active');
        $table->boolean('is_public');
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('product_prices', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->string('pricing_type');
        $table->integer('duration');
        $table->decimal('price', 18, 2);
        $table->timestamp('start_at')->nullable();
        $table->timestamp('end_at')->nullable();
        $table->boolean('is_active');
        $table->timestamps();
    });
    Schema::create('inventory_stocks', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedInteger('quantity_total');
        $table->unsignedInteger('quantity_reserved');
        $table->unsignedInteger('quantity_rented');
        $table->unsignedInteger('quantity_maintenance');
        $table->unsignedInteger('quantity_damaged');
        $table->unsignedInteger('quantity_lost');
        $table->timestamps();
    });
    Schema::create('product_units', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->string('status');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('booking_unit_allocations', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_unit_id');
        $table->string('status');
        $table->timestamp('start_at');
        $table->timestamp('end_at');
        $table->timestamps();
    });
    Schema::create('public_quotes', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->timestamp('starts_at');
        $table->timestamp('ends_at');
        $table->unsignedInteger('quantity');
        $table->json('request_snapshot');
        $table->json('pricing_snapshot');
        $table->string('request_hash', 64);
        $table->timestamp('expires_at');
        $table->timestamp('used_at')->nullable();
        $table->timestamps();
    });
    Schema::create('idempotency_records', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('idempotency_key', 64);
        $table->string('endpoint');
        $table->string('request_hash', 64);
        $table->string('status');
        $table->unsignedSmallInteger('response_status')->nullable();
        $table->json('response_body')->nullable();
        $table->string('resource_type')->nullable();
        $table->string('resource_id')->nullable();
        $table->timestamp('expires_at');
        $table->timestamps();
        $table->unique(['tenant_id', 'endpoint', 'idempotency_key']);
    });
    Schema::create('customers', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('phone');
        $table->string('email')->nullable();
        $table->string('status');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('bookings', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('customer_id');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->string('booking_number');
        $table->timestamp('start_at');
        $table->timestamp('end_at');
        $table->string('status');
        $table->string('booking_channel');
        $table->string('fulfillment_type');
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'delivery_fee', 'deposit_amount', 'charge_amount', 'total_amount', 'paid_amount', 'remaining_amount'] as $column) {
            $table->decimal($column, 18, 2)->default(0);
        }
        $table->string('payment_status');
        $table->text('customer_notes')->nullable();
        $table->text('internal_notes')->nullable();
        $table->uuid('public_id')->nullable();
        $table->string('tracking_token_hash', 64)->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('confirmed_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('booking_items', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('booking_id');
        $table->unsignedBigInteger('product_id');
        $table->string('product_name');
        $table->string('sku')->nullable();
        $table->string('inventory_type');
        $table->string('pricing_type');
        $table->integer('quantity');
        $table->integer('duration');
        foreach (['unit_price', 'subtotal', 'discount_amount', 'deposit_amount', 'total_amount'] as $column) {
            $table->decimal($column, 18, 2)->default(0);
        }
        $table->text('notes')->nullable();
        $table->timestamps();
    });
    Schema::create('stock_holds', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->uuid('quote_id')->nullable();
        $table->unsignedBigInteger('booking_id')->nullable();
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->timestamp('starts_at');
        $table->timestamp('ends_at');
        $table->unsignedInteger('quantity');
        $table->timestamp('expires_at');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('invoices', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('booking_id');
        $table->decimal('total_amount', 18, 2);
        $table->decimal('remaining_amount', 18, 2);
        $table->timestamps();
    });
    Schema::create('deposits', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('booking_id');
        $table->decimal('amount', 18, 2);
        $table->decimal('deducted_amount', 18, 2);
        $table->decimal('refunded_amount', 18, 2);
        $table->decimal('remaining_amount', 18, 2);
        $table->string('status');
        $table->timestamp('held_at')->nullable();
        $table->timestamp('refunded_at')->nullable();
        $table->timestamps();
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
        $table->timestamp('expired_at')->nullable();
        $table->timestamps();
    });
    Schema::create('booking_status_histories', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('booking_id');
        $table->string('from_status')->nullable();
        $table->string('to_status');
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('changed_by')->nullable();
        $table->timestamp('created_at');
    });
}

function seedPublicTransactionProduct(): void
{
    $now = now();
    DB::table('tenant_business_profiles')->insert([
        'tenant_id' => 'tenant-a',
        'business_name' => 'Tenant A',
        'currency' => 'IDR',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('rental_configurations')->insert([
        'tenant_id' => 'tenant-a',
        'rental_model' => 'per_day',
        'booking_strategy' => 'date_range',
        'allocation_strategy' => 'auto_assign',
        'slot_duration_minutes' => null,
        'allow_walk_in' => true,
        'allow_online_booking' => true,
        'realtime_availability' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $branchId = DB::table('branches')->insertGetId([
        'tenant_id' => 'tenant-a',
        'name' => 'Cabang Utama',
        'code' => 'MAIN',
        'is_active' => true,
        'is_public' => true,
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $productId = DB::table('products')->insertGetId([
        'tenant_id' => 'tenant-a',
        'public_id' => (string) Str::uuid(),
        'name' => 'Kamera Test',
        'slug' => 'kamera-test',
        'inventory_type' => 'quantity',
        'default_pricing_type' => 'daily',
        'deposit_amount' => 100000,
        'is_active' => true,
        'is_public' => true,
        'published_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('product_prices')->insert([
        'tenant_id' => 'tenant-a',
        'product_id' => $productId,
        'branch_id' => $branchId,
        'pricing_type' => 'daily',
        'duration' => 1,
        'price' => 100000,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('inventory_stocks')->insert([
        'tenant_id' => 'tenant-a',
        'product_id' => $productId,
        'branch_id' => $branchId,
        'quantity_total' => 10,
        'quantity_reserved' => 0,
        'quantity_rented' => 0,
        'quantity_maintenance' => 0,
        'quantity_damaged' => 0,
        'quantity_lost' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}
