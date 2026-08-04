<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravelcm\Subscriptions\Models\Subscription;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('tenancy.database.central_connection', 'sqlite');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Storage::fake('local');

    createProductCategoryCrudTestTables();

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

    $user = User::query()->create([
        'tenant_id' => 'tenant-a',
        'name' => 'Tenant Owner',
        'email' => 'owner@example.test',
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
        'user_id' => $user->getKey(),
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->headers = [
        'Authorization' => 'Bearer '.$user
            ->createToken('category-crud-test', ['tenant:access'])
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

test('product category master supports hierarchy CRUD and tenant isolation', function () {
    $root = $this->postJson('/api/tenant/tenant-a/categories', [
        'name' => 'Kamera',
        'description' => 'Peralatan kamera',
        'sort_order' => 10,
        'is_active' => true,
    ], $this->headers);

    $root->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.slug', 'kamera')
        ->assertJsonPath('data.tenant_id', 'tenant-a');

    $rootId = $root->json('data.id');

    $categoryImage = $this->post(
        "/api/tenant/tenant-a/categories/{$rootId}/image",
        ['image' => UploadedFile::fake()->image('camera.jpg', 640, 480)],
        $this->headers,
    )->assertOk();

    $categoryImagePath = $categoryImage->json('data.image_path');

    expect($categoryImagePath)->toStartWith("categories/{$rootId}/");
    Storage::disk('local')->assertExists($categoryImagePath);

    $this->delete(
        "/api/tenant/tenant-a/categories/{$rootId}/image",
        [],
        $this->headers,
    )->assertOk()
        ->assertJsonPath('data.image_path', null)
        ->assertJsonPath('data.image_url', null);

    Storage::disk('local')->assertMissing($categoryImagePath);

    $secondRoot = $this->postJson('/api/tenant/tenant-a/categories', [
        'name' => 'Kamera',
    ], $this->headers);

    $secondRoot->assertCreated()
        ->assertJsonPath('data.slug', 'kamera-1');

    $child = $this->postJson('/api/tenant/tenant-a/categories', [
        'parent_id' => $rootId,
        'name' => 'Mirrorless',
        'sort_order' => 1,
    ], $this->headers);

    $child->assertCreated()
        ->assertJsonPath('data.slug', 'mirrorless')
        ->assertJsonPath('data.parent.id', $rootId);

    $childId = $child->json('data.id');
    $tenantBCategoryId = 999;

    DB::table('categories')->insert([
        'id' => $tenantBCategoryId,
        'tenant_id' => 'tenant-b',
        'name' => 'Kategori Tenant B',
        'slug' => 'kategori-tenant-b',
        'sort_order' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(
        '/api/tenant/tenant-a/categories?roots_only=1',
        $this->headers,
    )->assertOk()
        ->assertJsonCount(2, 'data.data')
        ->assertJsonMissing(['id' => $tenantBCategoryId]);

    $this->getJson(
        "/api/tenant/tenant-a/categories/{$childId}",
        $this->headers,
    )->assertOk()
        ->assertJsonPath('data.parent.id', $rootId);

    $this->patchJson(
        "/api/tenant/tenant-a/categories/{$childId}",
        ['parent_id' => $childId],
        $this->headers,
    )->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->patchJson(
        "/api/tenant/tenant-a/categories/{$childId}",
        [
            'name' => 'Mirrorless Camera',
            'sort_order' => 2,
            'is_active' => false,
        ],
        $this->headers,
    )->assertOk()
        ->assertJsonPath('data.name', 'Mirrorless Camera')
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.is_active', false);

    $this->postJson('/api/tenant/tenant-a/categories', [
        'parent_id' => $tenantBCategoryId,
        'name' => 'Invalid Cross Tenant Child',
    ], $this->headers)->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->getJson(
        "/api/tenant/tenant-a/categories/{$tenantBCategoryId}",
        $this->headers,
    )->assertNotFound();

    $this->deleteJson(
        "/api/tenant/tenant-a/categories/{$childId}",
        [],
        $this->headers,
    )->assertOk()
        ->assertJsonPath('success', true);

    expect(DB::table('categories')->where('id', $childId)->value('deleted_at'))
        ->not->toBeNull();
});

test('product photos are private and primary photo is reassigned on delete', function () {
    $product = Product::query()->create([
        'tenant_id' => 'tenant-a',
        'name' => 'Sony A7 IV',
        'slug' => 'sony-a7-iv',
        'inventory_type' => 'serialized',
        'default_pricing_type' => 'daily',
    ]);

    $first = $this->post(
        "/api/tenant/tenant-a/products/{$product->getKey()}/images",
        [
            'image' => UploadedFile::fake()->image('front.jpg', 800, 600),
            'alt_text' => 'Tampak depan',
        ],
        $this->headers,
    )->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $firstId = $first->json('data.id');
    $firstPath = $first->json('data.image_path');

    expect($firstPath)->toStartWith("products/{$product->getKey()}/");
    Storage::disk('local')->assertExists($firstPath);

    $second = $this->post(
        "/api/tenant/tenant-a/products/{$product->getKey()}/images",
        [
            'image' => UploadedFile::fake()->image('side.webp', 800, 600),
            'is_primary' => true,
        ],
        $this->headers,
    )->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $secondId = $second->json('data.id');

    expect(DB::table('product_images')->where('id', $firstId)->value('is_primary'))
        ->toBe(0);

    $this->delete(
        "/api/tenant/tenant-a/products/{$product->getKey()}/images/{$secondId}",
        [],
        $this->headers,
    )->assertOk();

    expect(DB::table('product_images')->where('id', $firstId)->value('is_primary'))
        ->toBe(1);

    $this->delete(
        "/api/tenant/tenant-a/products/{$product->getKey()}/images/{$firstId}",
        [],
        $this->headers,
    )->assertOk();

    Storage::disk('local')->assertMissing($firstPath);
});

function createProductCategoryCrudTestTables(): void
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
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('name', 150);
        $table->string('slug', 150);
        $table->text('description')->nullable();
        $table->string('image_path')->nullable();
        $table->integer('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['tenant_id', 'slug']);
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
}
