<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantAuthentication\Application\ManageTenantAuthentication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravelcm\Subscriptions\Models\Subscription;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('tenancy.database.central_connection', 'sqlite');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    createTenantAuthenticationTestTables();

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
        'id' => '019c0000-0000-7000-8000-000000000001',
        'tenant_id' => 'tenant-a',
        'name' => 'Tenant Owner',
        'email' => 'owner@example.com',
        'password' => Hash::make('StrongPassword123!'),
        'is_active' => true,
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

test('logout revokes the current bearer token', function () {
    $token = loginTenantUser($this)->json('data.access_token');
    $headers = ['Authorization' => 'Bearer '.$token];

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
        $table->uuid('id')->primary();
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
        $table->uuidMorphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });
}
