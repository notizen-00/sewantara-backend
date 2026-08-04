<?php

use App\Models\Tenant;
use App\Modules\TenantOnboarding\Application\ConfigureTenantOnboarding;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravelcm\Subscriptions\Models\Subscription;
use Stancl\Tenancy\Tenancy;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    createTenantOnboardingFlowTables();

    $this->tenancy = app(Tenancy::class);
    $this->tenancy->getBootstrappersUsing = fn () => [];
    $subscription = new Subscription(['ends_at' => now()->addDays(14)]);
    $this->tenant = Mockery::mock(Tenant::class)->makePartial();
    $this->tenant->forceFill([
        'id' => 'tenant-a',
        'name' => 'Rental Kamera',
        'status' => 'onboarding',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
    ]);
    $this->tenant->shouldReceive('planSubscription')
        ->with('main')
        ->andReturn($subscription);
    $this->tenant->shouldReceive('update')
        ->andReturnUsing(function (array $attributes): bool {
            $this->tenant->forceFill($attributes);

            return true;
        });
    $this->tenancy->tenant = $this->tenant;
    $this->tenancy->initialized = true;
    app()->instance('currentTenant', $this->tenant);

    seedTenantOnboardingFlowData();
});

afterEach(function () {
    app()->forgetInstance('currentTenant');
    $this->tenancy->tenant = null;
    $this->tenancy->initialized = false;
    Mockery::close();
});

test('tenant completes every onboarding step before going live', function () {
    $onboarding = app(ConfigureTenantOnboarding::class);

    $onboarding->business('tenant-a', [
        'business_name' => 'Kamera Jember',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'branch_name' => 'Kamera Jember Pusat',
        'operating_hours' => [
            'monday' => ['open' => '08:00', 'close' => '21:00'],
        ],
    ]);
    $onboarding->inventoryCompleted('tenant-a');
    $onboarding->pricingCompleted('tenant-a');
    $onboarding->booking('tenant-a', [
        'allow_online_booking' => true,
        'allow_walk_in' => true,
        'enable_waiting_list' => false,
        'allocation_strategy' => 'auto_assign',
        'auto_reminder' => true,
        'auto_cancel_unpaid' => true,
        'auto_cancel_minutes' => 30,
    ]);
    $onboarding->payments('tenant-a', [
        [
            'method' => 'cash',
            'is_enabled' => true,
            'configuration' => null,
        ],
        [
            'method' => 'transfer',
            'is_enabled' => true,
            'configuration' => ['bank' => 'BCA'],
        ],
    ]);
    $result = $onboarding->goLive('tenant-a');

    expect($this->tenant->status)->toBe('active')
        ->and($result['status'])->toBe('completed')
        ->and($result['current_step'])->toBe('go_live')
        ->and($result['checklist'])->each->toBeTrue()
        ->and(DB::table('branches')->where('code', 'MAIN')->value('name'))
        ->toBe('Kamera Jember Pusat')
        ->and(DB::table('tenant_onboarding')->value('completed_at'))
        ->not->toBeNull();
});

test('tenant cannot go live while required onboarding steps are incomplete', function () {
    expect(fn () => app(ConfigureTenantOnboarding::class)->goLive('tenant-a'))
        ->toThrow(
            ValidationException::class,
            'Penyiapan awal belum lengkap',
        );

    expect($this->tenant->status)->toBe('onboarding');
});

function seedTenantOnboardingFlowData(): void
{
    $now = now();

    DB::table('tenant_business_profiles')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'primary_engine_code' => 'rental',
        'template_code' => 'camera_rental',
        'template_version' => 1,
        'business_name' => 'Rental Kamera',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'operating_hours' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('rental_configurations')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'engine_code' => 'rental',
        'rental_model' => 'per_day',
        'booking_strategy' => 'date_range',
        'allocation_strategy' => 'auto_assign',
        'slot_duration_minutes' => null,
        'enable_waiting_list' => false,
        'allow_walk_in' => true,
        'allow_online_booking' => true,
        'allow_extend_booking' => false,
        'realtime_availability' => true,
        'auto_reminder' => true,
        'auto_cancel_unpaid' => false,
        'auto_cancel_minutes' => null,
        'engine_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('tenant_onboarding')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'status' => 'in_progress',
        'current_step' => 'inventory_setup',
        'completed_steps' => json_encode([
            'business_template',
            'rental_configuration',
        ], JSON_THROW_ON_ERROR),
        'completed_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('tenant_payment_methods')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'method' => 'cash',
        'is_enabled' => true,
        'configuration' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('branches')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'name' => 'Rental Kamera',
        'code' => 'MAIN',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('product_units')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'deleted_at' => null,
    ]);
    DB::table('product_prices')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'pricing_type' => 'daily',
        'is_active' => true,
    ]);
}

function createTenantOnboardingFlowTables(): void
{
    Schema::create('tenant_business_profiles', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('primary_engine_code')->default('rental');
        $table->string('template_code');
        $table->integer('template_version');
        $table->string('business_name');
        $table->string('timezone');
        $table->string('currency');
        $table->json('operating_hours')->nullable();
        $table->timestamps();
    });
    Schema::create('rental_configurations', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('engine_code')->default('rental');
        $table->string('rental_model');
        $table->string('booking_strategy');
        $table->string('allocation_strategy');
        $table->integer('slot_duration_minutes')->nullable();
        $table->boolean('enable_waiting_list');
        $table->boolean('allow_walk_in');
        $table->boolean('allow_online_booking');
        $table->boolean('allow_extend_booking');
        $table->boolean('realtime_availability');
        $table->boolean('auto_reminder');
        $table->boolean('auto_cancel_unpaid');
        $table->integer('auto_cancel_minutes')->nullable();
        $table->integer('engine_version');
        $table->timestamps();
    });
    Schema::create('tenant_onboarding', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('status');
        $table->string('current_step');
        $table->json('completed_steps');
        $table->dateTime('completed_at')->nullable();
        $table->timestamps();
    });
    Schema::create('tenant_payment_methods', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('method');
        $table->boolean('is_enabled');
        $table->text('configuration')->nullable();
        $table->timestamps();
    });
    Schema::create('branches', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('code');
        $table->boolean('is_active');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('product_units', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->softDeletes();
    });
    Schema::create('inventory_stocks', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->integer('quantity_total');
    });
    Schema::create('product_prices', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('pricing_type');
        $table->boolean('is_active');
    });
}
