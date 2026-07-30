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
        $table->uuid('id')->primary();
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
        $table->uuid('user_id');
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        $table->primary(['branch_id', 'user_id']);
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

    $initializer->handle($tenantId, 'Kendo Kenceng', $owner);
    $initializer->handle($tenantId, 'Kendo Kenceng', $owner);

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
        'user_id' => $ownerId,
        'is_primary' => true,
    ]);
    $this->assertDatabaseCount('branch_users', 1);
});
