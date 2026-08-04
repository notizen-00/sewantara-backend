<?php

use App\Modules\TenantOnboarding\Infrastructure\Tenancy\InitializeTenantDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->string('name');
        $table->string('email');
        $table->string('phone')->nullable();
        $table->string('password');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('branches', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->string('name');
        $table->string('code');
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['tenant_id', 'code']);
    });

    Schema::create('branch_users', function (Blueprint $table): void {
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('user_id');
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        $table->primary(['branch_id', 'user_id']);
    });

    Schema::create('tenant_business_profiles', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->unique();
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
        $table->string('engine_code');
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
        $table->unique(['tenant_id', 'engine_code']);
    });

    Schema::create('tenant_onboarding', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->unique();
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
});

test('registration creates a main branch from the business name for the owner', function () {
    $tenantId = (string) Str::uuid();
    $ownerId = (string) Str::uuid();
    $owner = [
        'id' => $ownerId,
        'tenant_id' => $tenantId,
        'name' => 'Owner Kendo',
        'email' => 'owner@example.com',
        'phone' => '081234567890',
        'password' => bcrypt('StrongPassword123!'),
        'is_active' => true,
    ];

    $initializer = app(InitializeTenantDatabase::class);

    $onboarding = [
        'template_code' => 'camera_rental',
        'template_version' => 1,
        'configuration' => [
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
        ],
    ];

    $initializer->handle(
        $tenantId,
        'Kendo Kenceng',
        $owner,
        $onboarding,
        'Asia/Jakarta',
        'IDR',
    );
    $initializer->handle(
        $tenantId,
        'Kendo Kenceng',
        $owner,
        $onboarding,
        'Asia/Jakarta',
        'IDR',
    );

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('branches', [
        'tenant_id' => $tenantId,
        'name' => 'Kendo Kenceng',
        'code' => 'MAIN',
        'email' => 'owner@example.com',
        'phone' => '081234567890',
        'is_active' => true,
    ]);
    $this->assertDatabaseCount('branches', 1);
    $this->assertDatabaseHas('branch_users', [
        'branch_id' => DB::table('branches')->value('id'),
        'user_id' => DB::table('users')->value('id'),
        'is_primary' => true,
    ]);
    $this->assertDatabaseCount('branch_users', 1);
    $this->assertDatabaseHas('tenant_business_profiles', [
        'tenant_id' => $tenantId,
        'primary_engine_code' => 'rental',
        'template_code' => 'camera_rental',
        'business_name' => 'Kendo Kenceng',
    ]);
    $this->assertDatabaseHas('rental_configurations', [
        'tenant_id' => $tenantId,
        'engine_code' => 'rental',
        'rental_model' => 'per_day',
        'booking_strategy' => 'date_range',
        'allocation_strategy' => 'auto_assign',
    ]);
    $this->assertDatabaseHas('rental_configurations', [
        'tenant_id' => $tenantId,
        'engine_code' => 'booking',
        'rental_model' => 'session',
        'booking_strategy' => 'session',
    ]);
    $this->assertDatabaseCount('rental_configurations', 2);
    $this->assertDatabaseHas('tenant_onboarding', [
        'tenant_id' => $tenantId,
        'status' => 'in_progress',
        'current_step' => 'inventory_setup',
    ]);
    $this->assertDatabaseCount('tenant_payment_methods', 1);
});
