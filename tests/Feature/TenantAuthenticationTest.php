<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantAuthentication\Application\ManageTenantAuthentication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravelcm\Subscriptions\Models\Feature;
use Laravelcm\Subscriptions\Models\Plan;
use Laravelcm\Subscriptions\Models\Subscription;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('tenancy.database.central_connection', 'sqlite');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    createTenantAuthenticationTestTables();

    $this->tenancy = app(Tenancy::class);
    $this->tenancy->getBootstrappersUsing = fn () => [];

    $feature = new Feature;
    $feature->forceFill([
        'id' => 1,
        'slug' => 'branches.limit',
        'name' => ['id' => 'Batas Cabang'],
        'value' => '3',
        'sort_order' => 1,
    ]);

    $plan = new Plan;
    $plan->forceFill([
        'id' => 1,
        'name' => ['id' => 'Berkembang'],
        'slug' => 'growth',
        'description' => ['id' => 'Untuk usaha berkembang.'],
        'price' => 499000,
        'signup_fee' => 0,
        'currency' => 'IDR',
        'invoice_period' => 1,
        'invoice_interval' => 'month',
    ]);
    $plan->setRelation('features', new Collection([$feature]));

    $subscription = new Subscription([
        'name' => ['id' => 'Langganan Utama'],
        'slug' => 'main',
        'trial_ends_at' => now()->addDay(),
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);
    $subscription->forceFill(['id' => 1]);
    $subscription->setRelation('plan', $plan);
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

    DB::table('tenants')->insert([
        'id' => 'tenant-a',
        'name' => 'Tenant A',
        'slug' => 'tenant-a',
        'status' => 'active',
        'data' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->user = User::query()->create([
        'tenant_id' => 'tenant-a',
        'name' => 'Tenant Owner',
        'email' => 'owner@example.com',
        'password' => Hash::make('StrongPassword123!'),
        'is_active' => true,
    ]);

    DB::table('branches')->insert([
        [
            'id' => 1,
            'tenant_id' => 'tenant-a',
            'name' => 'Cabang Utama',
            'code' => 'MAIN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 2,
            'tenant_id' => 'tenant-a',
            'name' => 'Cabang Kedua',
            'code' => 'SECOND',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    DB::table('branch_users')->insert([
        [
            'branch_id' => 1,
            'user_id' => $this->user->getKey(),
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'branch_id' => 2,
            'user_id' => $this->user->getKey(),
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('roles')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'name' => 'Owner',
        'code' => 'owner',
        'is_system' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('permissions')->insert([
        'id' => 1,
        'name' => 'Kelola cabang',
        'code' => 'branches.manage',
        'module' => 'organization',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('role_permissions')->insert([
        'role_id' => 1,
        'permission_id' => 1,
    ]);
    DB::table('user_roles')->insert([
        'user_id' => $this->user->getKey(),
        'role_id' => 1,
        'branch_id' => null,
    ]);
});

afterEach(function () {
    app()->forgetInstance('currentTenant');
    app()->forgetInstance(PathTenantResolver::class);
    $this->tenancy->end();
    Mockery::close();
});

test('tenant login route does not require a tenant parameter', function () {
    /** @var Route $route */
    $route = app('router')->getRoutes()->getByName('tenant.auth.login');

    expect($route->uri())->toBe('api/tenant/auth/login')
        ->and($route->parameterNames())->toBe([])
        ->and($route->gatherMiddleware())
        ->not->toContain('tenant.path');
});

test('tenant user can login with credentials and receive a Sanctum bearer token', function () {
    $response = loginTenantUser($this);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'owner@example.com');

    $token = $response->json('data.access_token');

    expect($token)->toBeString()
        ->and($token)->toContain('|')
        ->and(DB::table('personal_access_tokens')->count())->toBe(1)
        ->and(User::query()->firstOrFail()->last_login_at)->not->toBeNull();
});

test('authentication returns a serializable user snapshot', function () {
    $result = app(ManageTenantAuthentication::class)->login(
        'owner@example.com',
        'StrongPassword123!',
        'pest',
    );

    expect($result['user'])->toBeArray()
        ->and($result['user']['email'])->toBe('owner@example.com');
});

test('central tenant detection treats email addresses case insensitively', function () {
    $this->postJson('http://localhost/api/tenant/auth/login', [
        'email' => 'OWNER@EXAMPLE.COM',
        'password' => 'StrongPassword123!',
    ])->assertOk()
        ->assertJsonPath('data.user.email', 'owner@example.com');
});

test('invalid credentials are rejected without issuing a token', function () {
    $this->postJson('http://localhost/api/tenant/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('protected tenant routes require a bearer token', function () {
    $this->getJson('/api/tenant/tenant-a/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

test('branch context requires a header and can switch through it', function () {
    $token = loginTenantUser($this)->json('data.access_token');
    $headers = ['Authorization' => 'Bearer '.$token];

    $this->getJson('/api/tenant/tenant-a/me', $headers)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'BRANCH_HEADER_REQUIRED');

    $this->getJson('/api/tenant/tenant-a/me', [
        ...$headers,
        'X-Branch-Id' => '1',
    ])->assertOk()
        ->assertHeader('X-Branch-Id', '1')
        ->assertJsonPath('data.branch.id', 1)
        ->assertJsonPath('data.subscription.status', 'trial')
        ->assertJsonPath('data.subscription.plan.slug', 'growth')
        ->assertJsonPath('data.subscription.plan.price', '499000.00')
        ->assertJsonPath('data.subscription.plan.features.0.slug', 'branches.limit')
        ->assertJsonPath('data.subscription.plan.features.0.value', '3');

    $this->getJson('/api/tenant/tenant-a/me', [
        ...$headers,
        'X-Branch-Id' => '2',
    ])->assertOk()
        ->assertJsonPath('data.branch.id', 2);
});

test('current tenant session includes user roles and permissions', function () {
    $token = loginTenantUser($this)->json('data.access_token');

    $this->getJson('/api/tenant/tenant-a/me', [
        'Authorization' => 'Bearer '.$token,
        'X-Branch-Id' => '1',
    ])->assertOk()
        ->assertJsonPath('data.user.roles.0.code', 'owner')
        ->assertJsonPath('data.user.roles.0.permissions.0.code', 'branches.manage');
});

test('current tenant session only includes global and active branch roles', function () {
    DB::table('roles')->insert([
        [
            'id' => 2,
            'tenant_id' => 'tenant-a',
            'name' => 'Branch Manager',
            'code' => 'branch-manager',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 3,
            'tenant_id' => 'tenant-a',
            'name' => 'Other Branch Staff',
            'code' => 'other-branch-staff',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    DB::table('user_roles')->insert([
        [
            'user_id' => $this->user->getKey(),
            'role_id' => 2,
            'branch_id' => 1,
        ],
        [
            'user_id' => $this->user->getKey(),
            'role_id' => 3,
            'branch_id' => 2,
        ],
    ]);

    $token = loginTenantUser($this)->json('data.access_token');

    $roles = $this->getJson('/api/tenant/tenant-a/me', [
        'Authorization' => 'Bearer '.$token,
        'X-Branch-Id' => '1',
    ])->assertOk()
        ->json('data.user.roles');

    expect(collect($roles)->pluck('code')->all())
        ->toBe(['owner', 'branch-manager']);
});

test('branch context rejects branches outside the user access list', function () {
    $token = loginTenantUser($this)->json('data.access_token');

    $this->getJson('/api/tenant/tenant-a/me', [
        'Authorization' => 'Bearer '.$token,
        'X-Branch-Id' => '999',
    ])->assertForbidden()
        ->assertJsonPath('error.code', 'BRANCH_ACCESS_DENIED');
});

test('logout revokes the current bearer token', function () {
    $token = loginTenantUser($this)->json('data.access_token');
    $headers = [
        'Authorization' => 'Bearer '.$token,
        'X-Branch-Id' => '1',
    ];

    $this->postJson('/api/tenant/tenant-a/auth/logout', [], $headers)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(DB::table('personal_access_tokens')->count())->toBe(0);

    $this->getJson('/api/tenant/tenant-a/me', $headers)
        ->assertUnauthorized();
});

function loginTenantUser($test): TestResponse
{
    return $test->postJson('http://localhost/api/tenant/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'StrongPassword123!',
        'device_name' => 'pest',
    ]);
}

function createTenantAuthenticationTestTables(): void
{
    Schema::create('tenants', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('status');
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->nullable()->index();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_login_at')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
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

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->nullable()->index();
        $table->string('name', 100);
        $table->string('code', 100);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
        $table->unique(['tenant_id', 'code']);
    });

    Schema::create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 150);
        $table->string('code', 150)->unique();
        $table->string('module', 100)->index();
        $table->timestamps();
    });

    Schema::create('role_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('permission_id');
        $table->primary(['role_id', 'permission_id']);
    });

    Schema::create('user_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->unique(['user_id', 'role_id', 'branch_id']);
        $table->index(['user_id', 'role_id']);
    });
}
