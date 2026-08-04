<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ProductEngine\Contracts\TenantEngineGate;
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

    app()->bind(TenantEngineGate::class, fn () => new class implements TenantEngineGate
    {
        public function isEnabled(string $tenantId, string $engineCode): bool
        {
            return true;
        }
    });

    $this->user = User::query()->create([
        'tenant_id' => 'tenant-a',
        'name' => 'Tenant Owner',
        'email' => 'owner@example.com',
        'password' => 'unused',
        'is_active' => true,
    ]);
    DB::table('branches')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'name' => 'Cabang Utama',
        'code' => 'MAIN',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('branch_users')->insert([
        'branch_id' => 1,
        'user_id' => $this->user->getKey(),
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->headers = [
        'Authorization' => 'Bearer '.$this->user
            ->createToken('product-crud-test', ['tenant:access'])
            ->plainTextToken,
        'X-Branch-Id' => '1',
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
        'engine_code' => 'rental',
        'product_type' => 'equipment',
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

    DB::table('branches')->insert([
        'id' => 2,
        'tenant_id' => 'tenant-a',
        'name' => 'Cabang Kedua',
        'code' => 'SECOND',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('product_units')->insert([
        [
            'tenant_id' => 'tenant-a',
            'product_id' => $productId,
            'branch_id' => 1,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'tenant_id' => 'tenant-a',
            'product_id' => $productId,
            'branch_id' => 2,
            'status' => 'damaged',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

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
        ->assertJsonPath('data.name', 'Sony Alpha A7 IV')
        ->assertJsonPath('data.stock_summary.quantity_total', 2)
        ->assertJsonPath('data.stock_summary.quantity_available', 1)
        ->assertJsonPath('data.stock_summary.quantity_damaged', 1)
        ->assertJsonPath('data.stock_by_branch.0.is_current_branch', false)
        ->assertJsonPath('data.stock_by_branch.1.is_current_branch', true);

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

test('inventory API adjusts conditions transfers stock and reports every branch', function () {
    DB::table('branches')->insert([
        'id' => 2,
        'tenant_id' => 'tenant-a',
        'name' => 'Cabang Kedua',
        'code' => 'SECOND',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $quantityProduct = $this->postJson('/api/tenant/tenant-a/products', [
        'name' => 'Tripod',
        'engine_code' => 'rental',
        'product_type' => 'equipment',
        'inventory_type' => 'quantity',
        'default_pricing_type' => 'daily',
    ], $this->headers)->assertCreated()->json('data.id');

    $this->postJson('/api/tenant/tenant-a/inventory/stocks/adjust', [
        'product_id' => $quantityProduct,
        'quantity' => 10,
        'reason_type' => 'purchase',
    ], $this->headers)->assertOk()
        ->assertJsonPath('data.quantity_total', 10)
        ->assertJsonPath('data.quantity_available', 10);

    $this->postJson('/api/tenant/tenant-a/inventory/stocks/adjust', [
        'product_id' => $quantityProduct,
        'quantity' => 2,
        'reason_type' => 'damaged',
        'notes' => 'Kaki tripod patah.',
    ], $this->headers)->assertOk()
        ->assertJsonPath('data.quantity_damaged', 2)
        ->assertJsonPath('data.quantity_available', 8);

    $this->postJson('/api/tenant/tenant-a/inventory/stocks/transfer', [
        'product_id' => $quantityProduct,
        'target_branch_id' => 2,
        'quantity' => 3,
        'notes' => 'Pemerataan stok.',
    ], $this->headers)->assertCreated()
        ->assertJsonPath('data.source_stock.quantity_total', 7)
        ->assertJsonPath('data.source_stock.quantity_available', 5)
        ->assertJsonPath('data.target_stock.quantity_total', 3)
        ->assertJsonPath('data.transfer.to_branch_id', 2);

    $this->getJson(
        "/api/tenant/tenant-a/products/{$quantityProduct}",
        $this->headers,
    )->assertOk()
        ->assertJsonPath('data.stock_summary.quantity_total', 10)
        ->assertJsonPath('data.stock_summary.quantity_available', 8)
        ->assertJsonPath('data.stock_summary.quantity_damaged', 2)
        ->assertJsonCount(2, 'data.stock_by_branch');

    $serializedProduct = $this->postJson('/api/tenant/tenant-a/products', [
        'name' => 'Sony A7 IV',
        'engine_code' => 'rental',
        'product_type' => 'equipment',
        'inventory_type' => 'serialized',
        'default_pricing_type' => 'daily',
    ], $this->headers)->assertCreated()->json('data.id');

    $unitId = DB::table('product_units')->insertGetId([
        'tenant_id' => 'tenant-a',
        'product_id' => $serializedProduct,
        'branch_id' => 1,
        'status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson(
        "/api/tenant/tenant-a/product-units/{$unitId}/transfer",
        [
            'target_branch_id' => 2,
            'notes' => 'Dipakai cabang kedua.',
        ],
        $this->headers,
    )->assertOk()
        ->assertJsonPath('data.branch_id', 2);
});

function createProductCrudTestTables(): void
{
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
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
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('branches', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('code');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('branch_users', function (Blueprint $table): void {
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('user_id');
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        $table->primary(['branch_id', 'user_id']);
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
        $table->string('engine_code')->default('rental');
        $table->string('product_type')->nullable();
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
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->string('status')->default('available');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('product_images', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->unsignedBigInteger('product_id');
        $table->string('image_path');
        $table->string('alt_text')->nullable();
        $table->boolean('is_primary')->default(false);
        $table->integer('sort_order')->default(0);
        $table->timestamps();
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

    Schema::create('inventory_stock_movements', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('booking_id')->nullable();
        $table->string('type');
        $table->integer('quantity');
        $table->integer('balance_before');
        $table->integer('balance_after');
        $table->string('reference_type')->nullable();
        $table->unsignedBigInteger('reference_id')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->dateTime('occurred_at');
        $table->dateTime('created_at')->nullable();
    });

    Schema::create('inventory_transfers', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('from_branch_id');
        $table->unsignedBigInteger('to_branch_id');
        $table->unsignedInteger('quantity');
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->dateTime('occurred_at');
        $table->timestamps();
    });

    Schema::create('product_movements', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->unsignedBigInteger('product_unit_id');
        $table->unsignedBigInteger('booking_id')->nullable();
        $table->string('type');
        $table->string('from_status')->nullable();
        $table->string('to_status')->nullable();
        $table->unsignedBigInteger('from_branch_id')->nullable();
        $table->unsignedBigInteger('to_branch_id')->nullable();
        $table->text('notes')->nullable();
        $table->dateTime('occurred_at');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->dateTime('created_at')->nullable();
    });
}
