<?php

use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\InitializeTenantByPath;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravelcm\Subscriptions\Models\Subscription;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;

beforeEach(function () {
    $this->tenancy = app(Tenancy::class);
    $this->tenancy->getBootstrappersUsing = fn () => [];
});

afterEach(function () {
    app()->forgetInstance('currentTenant');
    $this->tenancy->end();
});

function tenantRequest(?User $user = null): Request
{
    $request = Request::create('/api/v1/tenant-id/probe');
    $request->setUserResolver(fn () => $user);

    return $request;
}

function activateTenant(Tenancy $tenancy, Tenant $tenant): void
{
    $tenancy->tenant = $tenant;
    $tenancy->initialized = true;
}

function tenantModel(string $id = 'tenant-a', string $status = 'active'): Tenant
{
    return (new Tenant)->forceFill([
        'id' => $id,
        'status' => $status,
    ]);
}

function tenantUser(string $tenantId, bool $isActive = true): User
{
    return (new User)->forceFill([
        'id' => '019c0000-0000-7000-8000-000000000001',
        'tenant_id' => $tenantId,
        'is_active' => $isActive,
    ]);
}

test('path initialization exposes the tenant and always clears its context', function () {
    $tenant = tenantModel();
    $resolver = Mockery::mock(PathTenantResolver::class);
    $middleware = new InitializeTenantByPath($this->tenancy, $resolver);
    $request = Request::create('/api/v1/tenant-a/probe');
    $route = new Route(['GET'], 'api/v1/{tenant}/probe', fn () => null);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $resolver->shouldReceive('resolve')
        ->once()
        ->with($route)
        ->andReturn($tenant);

    $response = $middleware->handle($request, function (Request $request) use ($tenant) {
        expect(app('currentTenant'))->toBe($tenant)
            ->and($request->attributes->get('tenant'))->toBe($tenant);

        return response()->json(['success' => true]);
    });

    expect($response->getStatusCode())->toBe(200)
        ->and($this->tenancy->initialized)->toBeFalse()
        ->and(app()->bound('currentTenant'))->toBeFalse();
});

test('unknown path tenant returns the documented safe error', function () {
    $resolver = Mockery::mock(PathTenantResolver::class);
    $middleware = new InitializeTenantByPath($this->tenancy, $resolver);
    $request = Request::create('/api/v1/missing/probe');
    $route = new Route(['GET'], 'api/v1/{tenant}/probe', fn () => null);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $resolver->shouldReceive('resolve')
        ->once()
        ->andThrow(new TenantCouldNotBeIdentifiedByPathException('missing'));

    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error']['code'])->toBe('TENANT_NOT_FOUND')
        ->and($this->tenancy->initialized)->toBeFalse();
});

test('user tenant must exactly match the initialized tenant', function () {
    activateTenant($this->tenancy, tenantModel('tenant-a'));
    $middleware = app(EnsureUserBelongsToTenant::class);

    $unauthenticated = $middleware->handle(
        tenantRequest(),
        fn () => response()->noContent(),
    );
    $crossTenant = $middleware->handle(
        tenantRequest(tenantUser('tenant-b')),
        fn () => response()->noContent(),
    );
    $allowed = $middleware->handle(
        tenantRequest(tenantUser('tenant-a')),
        fn () => response()->json(['success' => true]),
    );

    expect($unauthenticated->getStatusCode())->toBe(401)
        ->and($crossTenant->getStatusCode())->toBe(403)
        ->and($crossTenant->getData(true)['error']['code'])->toBe('TENANT_ACCESS_DENIED')
        ->and($allowed->getStatusCode())->toBe(200);
});

test('inactive users and tenants are locked', function () {
    activateTenant($this->tenancy, tenantModel('tenant-a', 'suspended'));

    $inactiveUser = app(EnsureUserBelongsToTenant::class)->handle(
        tenantRequest(tenantUser('tenant-a', false)),
        fn () => response()->noContent(),
    );
    $suspendedTenant = app(EnsureTenantIsActive::class)->handle(
        tenantRequest(tenantUser('tenant-a')),
        fn () => response()->noContent(),
    );

    expect($inactiveUser->getStatusCode())->toBe(403)
        ->and($inactiveUser->getData(true)['error']['code'])->toBe('USER_INACTIVE')
        ->and($suspendedTenant->getStatusCode())->toBe(423)
        ->and($suspendedTenant->getData(true)['error']['code'])->toBe('TENANT_SUSPENDED');
});

test('main subscription must exist and be active', function () {
    $expiredSubscription = new Subscription([
        'ends_at' => now()->subMinute(),
    ]);
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => 'tenant-a', 'status' => 'active']);
    $tenant->shouldReceive('planSubscription')
        ->with('main')
        ->andReturn(null, $expiredSubscription, new Subscription([
            'ends_at' => now()->addMinute(),
        ]));
    activateTenant($this->tenancy, $tenant);

    $required = app(EnsureTenantSubscriptionActive::class)->handle(
        tenantRequest(tenantUser('tenant-a')),
        fn () => response()->noContent(),
    );
    $expired = app(EnsureTenantSubscriptionActive::class)->handle(
        tenantRequest(tenantUser('tenant-a')),
        fn () => response()->noContent(),
    );
    $allowed = app(EnsureTenantSubscriptionActive::class)->handle(
        tenantRequest(tenantUser('tenant-a')),
        fn () => response()->json(['success' => true]),
    );

    expect($required->getStatusCode())->toBe(403)
        ->and($required->getData(true)['error']['code'])->toBe('SUBSCRIPTION_REQUIRED')
        ->and($expired->getStatusCode())->toBe(423)
        ->and($expired->getData(true)['error']['code'])->toBe('SUBSCRIPTION_EXPIRED')
        ->and($allowed->getStatusCode())->toBe(200);
});

test('tenant API routes use the complete guard chain in order', function () {
    $route = app('router')->getRoutes()->getByName('products.index');
    $expected = [
        InitializeTenantByPath::class,
        EnsureUserBelongsToTenant::class,
        EnsureTenantIsActive::class,
        EnsureTenantSubscriptionActive::class,
    ];

    $middleware = app('router')->gatherRouteMiddleware($route);
    $actual = array_values(array_intersect($middleware, $expected));

    expect($actual)->toBe($expected);
});
