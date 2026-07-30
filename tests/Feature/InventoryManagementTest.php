<?php

use App\Modules\Bookings\Application\ManageBookings;
use App\Modules\Bookings\Application\ManageBookingStatus;
use App\Modules\Inventory\Application\ManageMaintenance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    createInventoryManagementTables();
    seedInventoryManagementData();
});

test('booking automatically records serialized and quantity inventory lifecycle', function () {
    $booking = app(ManageBookings::class)->create('tenant-a', null, [
        'customer_id' => 1,
        'branch_id' => 1,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(3),
        'fulfillment_type' => 'pickup',
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_ids' => [1],
            ],
            [
                'product_id' => 2,
                'quantity' => 2,
            ],
        ],
    ]);

    expect($booking->status)->toBe('pending')
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('reserved')
        ->and(DB::table('inventory_stocks')->where('product_id', 2)->value('quantity_reserved'))
        ->toBe(2)
        ->and(DB::table('product_movements')->where('type', 'booking_reserved')->count())
        ->toBe(1)
        ->and(DB::table('inventory_stock_movements')->where('type', 'booking_reserved')->count())
        ->toBe(1);

    $checkedOut = app(ManageBookingStatus::class)->checkOut($booking, null);

    expect($checkedOut->status)->toBe('ongoing')
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('rented')
        ->and(DB::table('inventory_stocks')->where('product_id', 2)->value('quantity_reserved'))
        ->toBe(0)
        ->and(DB::table('inventory_stocks')->where('product_id', 2)->value('quantity_rented'))
        ->toBe(2);

    $returned = app(ManageBookingStatus::class)->return($checkedOut, null);

    expect($returned->status)->toBe('completed')
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('available')
        ->and(DB::table('inventory_stocks')->where('product_id', 2)->value('quantity_rented'))
        ->toBe(0)
        ->and(DB::table('product_movements')->where('type', 'booking_returned')->count())
        ->toBe(1)
        ->and(DB::table('inventory_stock_movements')->where('type', 'booking_returned')->count())
        ->toBe(1);
});

test('maintenance blocks a unit and records its history until completion', function () {
    $manager = app(ManageMaintenance::class);
    $maintenance = $manager->create('tenant-a', null, [
        'product_unit_id' => 1,
        'type' => 'service',
        'title' => 'Service sensor kamera',
        'cost' => 100000,
        'scheduled_at' => now()->addDay(),
    ]);

    $started = $manager->start($maintenance, null);

    expect($started->status)->toBe('in_progress')
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('maintenance')
        ->and(DB::table('product_movements')->where('type', 'maintenance_started')->count())
        ->toBe(1);

    $completed = $manager->complete($started, null, [
        'condition' => 'good',
        'unit_status' => 'available',
        'cost' => 125000,
    ]);

    expect($completed->status)->toBe('completed')
        ->and($completed->cost)->toBe('125000.00')
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('available')
        ->and(DB::table('product_movements')->where('type', 'maintenance_completed')->count())
        ->toBe(1);
});

test('cancelling a booking releases reservations and records the cancellation', function () {
    $booking = app(ManageBookings::class)->create('tenant-a', null, [
        'customer_id' => 1,
        'branch_id' => 1,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(3),
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_ids' => [1],
            ],
            [
                'product_id' => 2,
                'quantity' => 2,
            ],
        ],
    ]);

    $cancelled = app(ManageBookingStatus::class)->cancel(
        $booking,
        null,
        'Customer membatalkan.',
    );

    expect($cancelled->status)->toBe('cancelled')
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('available')
        ->and(DB::table('inventory_stocks')->where('product_id', 2)->value('quantity_reserved'))
        ->toBe(0)
        ->and(DB::table('product_movements')->where('type', 'booking_cancelled')->count())
        ->toBe(1)
        ->and(DB::table('inventory_stock_movements')->where('type', 'booking_cancelled')->count())
        ->toBe(1)
        ->and(DB::table('booking_status_histories')->where('to_status', 'cancelled')->value('notes'))
        ->toBe('Customer membatalkan.');
});

test('rental engine automatically assigns serialized units when configured', function () {
    DB::table('rental_configurations')->update([
        'allocation_strategy' => 'auto_assign',
    ]);

    $booking = app(ManageBookings::class)->create('tenant-a', null, [
        'customer_id' => 1,
        'branch_id' => 1,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 1,
            ],
        ],
    ]);

    expect($booking->allocations)->toHaveCount(1)
        ->and($booking->allocations->first()->product_unit_id)->toBe(1)
        ->and(DB::table('product_units')->where('id', 1)->value('status'))
        ->toBe('reserved');
});

function seedInventoryManagementData(): void
{
    $now = now();

    DB::table('customers')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'name' => 'Customer',
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('products')->insert([
        [
            'id' => 1,
            'tenant_id' => 'tenant-a',
            'name' => 'Kamera',
            'slug' => 'kamera',
            'sku' => 'CAM-001',
            'inventory_type' => 'serialized',
            'default_pricing_type' => 'daily',
            'deposit_amount' => 100000,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => 2,
            'tenant_id' => 'tenant-a',
            'name' => 'Tripod',
            'slug' => 'tripod',
            'sku' => 'TRI-001',
            'inventory_type' => 'quantity',
            'default_pricing_type' => 'daily',
            'deposit_amount' => 50000,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('product_prices')->insert([
        [
            'id' => '019c0000-0000-7000-8000-000000000011',
            'tenant_id' => 'tenant-a',
            'product_id' => 1,
            'branch_id' => 1,
            'pricing_type' => 'daily',
            'duration' => 1,
            'price' => 200000,
            'is_active' => true,
        ],
        [
            'id' => '019c0000-0000-7000-8000-000000000012',
            'tenant_id' => 'tenant-a',
            'product_id' => 2,
            'branch_id' => 1,
            'pricing_type' => 'daily',
            'duration' => 1,
            'price' => 50000,
            'is_active' => true,
        ],
    ]);

    DB::table('product_units')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'product_id' => 1,
        'branch_id' => 1,
        'unit_code' => 'CAM-UNIT-001',
        'status' => 'available',
        'condition' => 'good',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('inventory_stocks')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'product_id' => 2,
        'branch_id' => 1,
        'quantity_total' => 10,
        'quantity_reserved' => 0,
        'quantity_rented' => 0,
        'quantity_maintenance' => 0,
        'quantity_damaged' => 0,
        'quantity_lost' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('rental_configurations')->insert([
        'id' => '019c0000-0000-7000-8000-000000000013',
        'tenant_id' => 'tenant-a',
        'rental_model' => 'per_day',
        'booking_strategy' => 'date_range',
        'allocation_strategy' => 'manual',
        'slot_duration_minutes' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function createInventoryManagementTables(): void
{
    Schema::create('rental_configurations', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->string('rental_model');
        $table->string('booking_strategy');
        $table->string('allocation_strategy');
        $table->unsignedInteger('slot_duration_minutes')->nullable();
        $table->boolean('allow_walk_in')->default(true);
        $table->boolean('allow_online_booking')->default(true);
        $table->boolean('realtime_availability')->default(true);
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('status');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('slug');
        $table->string('sku')->nullable();
        $table->string('inventory_type');
        $table->string('default_pricing_type');
        $table->decimal('deposit_amount')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('product_prices', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->string('pricing_type');
        $table->integer('duration')->default(1);
        $table->decimal('price');
        $table->boolean('is_active');
    });

    Schema::create('product_units', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->string('unit_code');
        $table->string('status');
        $table->string('condition');
        $table->unsignedBigInteger('current_meter')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('inventory_stocks', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedInteger('quantity_total')->default(0);
        $table->unsignedInteger('quantity_reserved')->default(0);
        $table->unsignedInteger('quantity_rented')->default(0);
        $table->unsignedInteger('quantity_maintenance')->default(0);
        $table->unsignedInteger('quantity_damaged')->default(0);
        $table->unsignedInteger('quantity_lost')->default(0);
        $table->timestamps();
    });

    Schema::create('bookings', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->unsignedBigInteger('customer_id');
        $table->uuid('created_by')->nullable();
        $table->string('booking_number');
        $table->dateTime('start_at');
        $table->dateTime('end_at');
        $table->dateTime('actual_start_at')->nullable();
        $table->dateTime('actual_end_at')->nullable();
        $table->string('status');
        $table->string('booking_channel')->default('walk_in');
        $table->string('fulfillment_type');
        $table->decimal('subtotal')->default(0);
        $table->decimal('discount_amount')->default(0);
        $table->decimal('tax_amount')->default(0);
        $table->decimal('delivery_fee')->default(0);
        $table->decimal('deposit_amount')->default(0);
        $table->decimal('charge_amount')->default(0);
        $table->decimal('total_amount')->default(0);
        $table->decimal('paid_amount')->default(0);
        $table->decimal('remaining_amount')->default(0);
        $table->string('payment_status');
        $table->text('customer_notes')->nullable();
        $table->text('internal_notes')->nullable();
        $table->dateTime('confirmed_at')->nullable();
        $table->dateTime('cancelled_at')->nullable();
        $table->dateTime('completed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('booking_items', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->uuid('booking_id');
        $table->unsignedBigInteger('product_id');
        $table->string('product_name');
        $table->string('sku')->nullable();
        $table->string('inventory_type');
        $table->string('pricing_type');
        $table->integer('quantity');
        $table->integer('duration');
        $table->decimal('unit_price');
        $table->decimal('subtotal');
        $table->decimal('discount_amount')->default(0);
        $table->decimal('deposit_amount')->default(0);
        $table->decimal('total_amount');
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('booking_unit_allocations', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->uuid('booking_id');
        $table->uuid('booking_item_id');
        $table->unsignedBigInteger('product_unit_id');
        $table->dateTime('start_at');
        $table->dateTime('end_at');
        $table->string('status');
        $table->dateTime('allocated_at');
        $table->dateTime('checked_out_at')->nullable();
        $table->dateTime('returned_at')->nullable();
        $table->timestamps();
    });

    Schema::create('booking_status_histories', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->uuid('booking_id');
        $table->string('from_status')->nullable();
        $table->string('to_status');
        $table->text('notes')->nullable();
        $table->uuid('changed_by')->nullable();
        $table->dateTime('created_at');
    });

    Schema::create('invoices', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->uuid('booking_id');
        $table->string('invoice_number');
        $table->date('issue_date');
        $table->date('due_date')->nullable();
        $table->decimal('subtotal')->default(0);
        $table->decimal('discount_amount')->default(0);
        $table->decimal('tax_amount')->default(0);
        $table->decimal('total_amount')->default(0);
        $table->decimal('paid_amount')->default(0);
        $table->decimal('remaining_amount')->default(0);
        $table->string('status');
        $table->timestamps();
    });

    Schema::create('product_movements', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_unit_id');
        $table->uuid('booking_id')->nullable();
        $table->string('type');
        $table->string('from_status')->nullable();
        $table->string('to_status')->nullable();
        $table->unsignedBigInteger('from_branch_id')->nullable();
        $table->unsignedBigInteger('to_branch_id')->nullable();
        $table->text('notes')->nullable();
        $table->dateTime('occurred_at');
        $table->uuid('created_by')->nullable();
        $table->dateTime('created_at');
    });

    Schema::create('inventory_stock_movements', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->uuid('booking_id')->nullable();
        $table->string('type');
        $table->integer('quantity');
        $table->integer('balance_before');
        $table->integer('balance_after');
        $table->string('reference_type')->nullable();
        $table->string('reference_id')->nullable();
        $table->text('notes')->nullable();
        $table->uuid('created_by')->nullable();
        $table->dateTime('occurred_at');
        $table->dateTime('created_at');
    });

    Schema::create('maintenance_records', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_unit_id');
        $table->string('type');
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('vendor')->nullable();
        $table->decimal('cost')->default(0);
        $table->dateTime('scheduled_at')->nullable();
        $table->dateTime('started_at')->nullable();
        $table->dateTime('completed_at')->nullable();
        $table->string('status');
        $table->uuid('created_by')->nullable();
        $table->timestamps();
    });
}
