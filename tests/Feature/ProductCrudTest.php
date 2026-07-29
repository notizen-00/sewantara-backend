<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravelcm\Subscriptions\Models\Subscription;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('tenancy.database.central_connection', 'sqlite');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    createProductCrudTestTables();

    $this->tenancy = app(Tenancy::class);
    $this->tenancy->getBootstrappersUsing = fn () => [];

    $subscription = new Subscription(['ends_at' => now()->addDay()]);
    $this->tenant = Mockery::mock(Tenant::class)->makePartial();
    $this->tenant->forceFill([
        'id' => 'tenant-a',
        'name' => 'Tenant A',
        'status' => 'active',
    ]);
    $this->tenant->shouldReceive('planSubscription')
        ->with('main')
        ->andReturn($subscription);

    $resolver = Mockery::mock(PathTenantResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($this->tenant);
    app()->instance(PathTenantResolver::class, $resolver);

    $this->user = User::query()->create([
        'id' => '019c0000-0000-7000-8000-000000000001',
        'tenant_id' => 'tenant-a',
        'name' => 'Tenant Owner',
        'email' => 'owner@example.com',
        'password' => 'unused',
        'is_active' => true,
    ]);
    $this->headers = [
        'Authorization' => 'Bearer '.$this->user
            ->createToken('product-crud-test', ['tenant:access'])
            ->plainTextToken,
    ];
});

afterEach(function () {
    app()->forgetInstance('currentTenant');
    app()->forgetInstance(PathTenantResolver::class);
    $this->tenancy->end();
    Mockery::close();
});

test('product master supports complete CRUD and tenant isolation', function () {
    $created = $this->postJson('/api/tenant/tenant-a/products', [
        'name' => 'Sony Alpha A7 IV',
        'sku' => 'CAM-SONY-A7IV',
        'brand' => 'Sony',
        'model' => 'A7 IV',
        'inventory_type' => 'serialized',
        'default_pricing_type' => 'daily',
        'minimum_rental_duration' => 1,
        'deposit_amount' => 1000000,
        'late_fee_amount' => 250000,
        'is_featured' => true,
        'is_active' => true,
    ], $this->headers);

    $created->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.slug', 'sony-alpha-a7-iv')
        ->assertJsonPath('data.tenant_id', 'tenant-a');

    $productId = $created->json('data.id');

    DB::table('products')->insert([
        'id' => 999,
        'tenant_id' => 'tenant-b',
        'name' => 'Tenant B Product',
        'slug' => 'tenant-b-product',
        'inventory_type' => 'quantity',
        'default_pricing_type' => 'daily',
        'minimum_rental_duration' => 1,
        'deposit_amount' => 0,
        'late_fee_amount' => 0,
        'is_featured' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson('/api/tenant/tenant-a/products', $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $productId);

    $this->getJson(
        "/api/tenant/tenant-a/products/{$productId}",
        $this->headers,
    )->assertOk()
        ->assertJsonPath('data.name', 'Sony Alpha A7 IV');

    $this->patchJson("/api/tenant/tenant-a/products/{$productId}", [
        'name' => 'Sony Alpha A7 IV Updated',
        'deposit_amount' => 1250000,
        'is_featured' => false,
    ], $this->headers)->assertOk()
        ->assertJsonPath('data.name', 'Sony Alpha A7 IV Updated')
        ->assertJsonPath('data.deposit_amount', '1250000.00');

    $this->deleteJson(
        "/api/tenant/tenant-a/products/{$productId}",
        [],
        $this->headers,
    )->assertOk()
        ->assertJsonPath('success', true);

    expect(DB::table('products')->where('id', $productId)->value('deleted_at'))
        ->not->toBeNull();
});

function createProductCrudTestTables(): void
{
    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('tenant_id')->nullable()->index();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('personal_access_tokens', function (Blueprint $table): void {
        $table->id();
        $table->uuidMorphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->string('name');
        $table->string('slug');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->unsignedBigInteger('category_id')->nullable();
        $table->string('name');
        $table->string('slug');
        $table->string('sku')->nullable();
        $table->string('brand')->nullable();
        $table->string('model')->nullable();
        $table->text('description')->nullable();
        $table->string('inventory_type');
        $table->string('default_pricing_type');
        $table->integer('minimum_rental_duration')->default(1);
        $table->decimal('deposit_amount', 18, 2)->default(0);
        $table->decimal('late_fee_amount', 18, 2)->default(0);
        $table->boolean('is_featured')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('product_units', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->unsignedBigInteger('product_id');
        $table->timestamps();
        $table->softDeletes();
    });
}
