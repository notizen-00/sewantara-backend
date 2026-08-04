<?php

use App\Http\Middleware\AssignPublicRequestId;
use App\Http\Middleware\AuthenticateBffService;
use App\Http\Middleware\InitializePublicTenancy;
use App\Http\Middleware\ObservePublicApiRequest;
use App\Http\Middleware\SetPublicTenantLocale;
use App\Http\Middleware\TrustConfiguredProxies;
use App\Http\Middleware\ValidatePublicTenantEligibility;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\PublicApiResponse;
use App\Support\TenantSchemaGuard;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Tenancy;

test('health and authenticated readiness use request IDs without leaving probe keys', function () {
    config()->set('public-api.internal_health_token', 'internal-health-secret');
    config()->set('public-api.readiness_cache_store', 'array');
    $requestId = (string) Str::ulid();

    $this->getJson('/healthz')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonStructure(['meta' => ['request_id']]);

    $this->withHeaders([
        'Authorization' => 'Bearer internal-health-secret',
        'X-Request-Id' => $requestId,
    ])->getJson('/readyz')
        ->assertOk()
        ->assertHeader('X-Request-Id', $requestId)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('meta.request_id', $requestId)
        ->assertJsonPath('data.status', 'ready');

    expect(Cache::store('array')->has('readiness-probe:'.$requestId))
        ->toBeFalse();
});

test('readiness fails closed without its separate internal credential', function () {
    config()->set('public-api.internal_health_token', 'internal-health-secret');

    $this->getJson('/readyz')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('error.code', 'AUTH_SERVICE_REQUIRED')
        ->assertJsonStructure(['meta' => ['request_id']]);
});

test('public exceptions and unmatched routes use the standard safe envelope', function () {
    Route::middleware([
        AssignPublicRequestId::class,
        'force.json',
    ])->get('/v1/public/_foundation/failure', function (): never {
        throw new RuntimeException('sensitive internal detail');
    });

    $failure = $this->get('/v1/public/_foundation/failure');
    $failure->assertStatus(500)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('error.code', 'INTERNAL_ERROR')
        ->assertJsonMissing(['message' => 'sensitive internal detail'])
        ->assertJsonStructure(['meta' => ['request_id']]);
    expect($failure->headers->get('X-Request-Id'))
        ->toBe($failure->json('meta.request_id'));

    $missing = $this->get('/v1/public/_foundation/not-found');
    $missing->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
        ->assertJsonStructure(['meta' => ['request_id']]);
    expect($missing->headers->get('X-Request-Id'))
        ->toBe($missing->json('meta.request_id'));
});

test('public validation exceptions expose fields in the standard envelope', function () {
    Route::middleware([
        AssignPublicRequestId::class,
        'force.json',
    ])->post('/v1/public/_foundation/validation', function (): never {
        throw ValidationException::withMessages([
            'customer.phone' => ['Nomor telepon tidak valid.'],
        ]);
    });

    $response = $this->postJson('/v1/public/_foundation/validation');
    $response
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['meta' => ['request_id']]);

    expect($response->json('error.fields')['customer.phone'][0])
        ->toBe('Nomor telepon tidak valid.');
});

test('BFF authentication supports labeled token rotation and CIDR allowlists', function () {
    config()->set('public-api.bff_tokens', [
        'current' => 'current-secret',
        'previous' => 'previous-secret',
    ]);
    config()->set('public-api.trusted_bff_ips', ['10.20.0.0/16']);
    $middleware = app(AuthenticateBffService::class);

    foreach ([
        'current-secret' => 'tenant-web-current',
        'previous-secret' => 'tenant-web-previous',
    ] as $token => $serviceId) {
        $request = Request::create(
            '/v1/public/tenant',
            'GET',
            server: ['REMOTE_ADDR' => '10.20.30.40'],
        );
        $request->headers->set('Authorization', 'Bearer '.$token);

        $response = $middleware->handle(
            $request,
            function (Request $request) use ($serviceId) {
                expect($request->attributes->get('bff_service_id'))
                    ->toBe($serviceId);

                return response()->json(['success' => true]);
            },
        );

        expect($response->getStatusCode())->toBe(200);
    }

    $untrusted = Request::create(
        '/v1/public/tenant',
        'GET',
        server: ['REMOTE_ADDR' => '10.21.30.40'],
    );
    $untrusted->headers->set('Authorization', 'Bearer current-secret');
    $response = $middleware->handle(
        $untrusted,
        fn () => response()->noContent(),
    );

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['error']['code'])
        ->toBe('AUTH_SERVICE_INVALID');
});

test('configured proxies trust client IP but never forwarded host', function () {
    config()->set('public-api.trusted_proxy_ips', ['10.0.0.0/8']);
    $request = Request::create('https://api.sewantara.id/healthz');
    $request->server->set('REMOTE_ADDR', '10.10.10.10');
    $request->headers->set('X-Forwarded-For', '203.0.113.25');
    $request->headers->set('X-Forwarded-Host', 'evil.example');

    $response = app(TrustConfiguredProxies::class)->handle(
        $request,
        function (Request $request) {
            expect($request->ip())->toBe('203.0.113.25')
                ->and($request->getHost())->toBe('api.sewantara.id');

            return response()->noContent();
        },
    );

    expect($response->getStatusCode())->toBe(204);
});

test('public response canonical metadata cannot be overridden', function () {
    $request = Request::create('/v1/public/tenant');
    $request->attributes->set('request_id', (string) Str::ulid());
    $request->attributes->set('tenant_slug', 'tenant-benar');

    $response = PublicApiResponse::success($request, [], [
        'request_id' => 'spoofed',
        'tenant' => 'spoofed',
        'page' => 1,
    ]);
    $payload = $response->getData(true);

    expect($payload['meta']['request_id'])
        ->toBe($request->attributes->get('request_id'))
        ->and($payload['meta']['tenant'])->toBe('tenant-benar')
        ->and($payload['meta']['page'])->toBe(1);
});

test('public cache headers vary by tenant and keep private routes no-store', function () {
    config()->set('public-api.response_cache.cacheable_routes', [
        'foundation.cacheable',
    ]);

    Route::get('/v1/public/_foundation/cacheable', fn () => response()->json([
        'success' => true,
    ]))->name('foundation.cacheable');
    Route::post('/v1/public/_foundation/private', fn () => response()->json([
        'success' => true,
    ]))->name('foundation.private');

    $cacheable = $this->getJson('/v1/public/_foundation/cacheable');
    $cacheable->assertOk();
    $cacheControl = (string) $cacheable->headers->get('Cache-Control');
    $vary = (string) $cacheable->headers->get('Vary');

    expect($cacheControl)->toContain('public')
        ->toContain('max-age=60')
        ->toContain('s-maxage=300')
        ->toContain('stale-while-revalidate=60')
        ->and($vary)->toContain('Accept')
        ->toContain('X-Tenant')
        ->toContain('X-Tenant-Host');

    $private = $this->postJson('/v1/public/_foundation/private');
    $private->assertOk();
    expect((string) $private->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
});

test('structured public request logs exclude credentials and bodies', function () {
    Log::spy();
    $request = Request::create('/v1/public/tenant', 'GET');
    $request->headers->set('Authorization', 'Bearer top-secret');
    $request->attributes->set('request_id', (string) Str::ulid());
    $request->attributes->set('tenant_slug', 'kamera-jember');
    $request->attributes->set('tenant_host', 'kamera-jember.sewantara.id');
    $request->attributes->set('bff_service_id', 'tenant-web-current');
    $tenant = (new Tenant)->forceFill(['id' => 'tenant-observed']);
    $request->attributes->set('resolved_tenant', $tenant);
    $route = new Illuminate\Routing\Route(
        ['GET'],
        'v1/public/tenant',
        fn () => null,
    );
    $route->name('public.v1.tenant');
    $request->setRouteResolver(fn () => $route);
    $middleware = app(ObservePublicApiRequest::class);
    $response = $middleware->handle(
        $request,
        fn () => response()->json(['success' => true]),
    );
    $middleware->terminate($request, $response);

    Log::shouldHaveReceived('info')->once()->with(
        'PUBLIC_API_REQUEST',
        Mockery::on(function (array $context): bool {
            return $context['tenant_id'] === 'tenant-observed'
                && $context['tenant_slug'] === 'kamera-jember'
                && $context['status_code'] === 200
                && $context['bff_service_id'] === 'tenant-web-current'
                && ! str_contains(json_encode($context), 'top-secret');
        }),
    );
});

test('invalid request IDs are replaced with canonical ULIDs', function () {
    $request = Request::create('/v1/public/tenant');
    $request->headers->set('X-Request-Id', str_repeat('Z', 26));

    $requestId = PublicApiResponse::requestId($request);

    expect($requestId)->not->toBe(str_repeat('Z', 26))
        ->and(Str::isUlid($requestId))->toBeTrue();
});

test('tenant locale maps BCP 47 values and is restored after the request', function () {
    app()->setLocale('en');
    $tenant = (new Tenant)->forceFill([
        'id' => 'tenant-locale',
        'locale' => 'id-ID',
    ]);
    $request = Request::create('/v1/public/tenant');
    $request->attributes->set('tenant', $tenant);

    app(SetPublicTenantLocale::class)->handle($request, function () {
        expect(app()->getLocale())->toBe('id');

        return response()->noContent();
    });

    expect(app()->getLocale())->toBe('en');
});

test('active but unprovisioned tenants fail eligibility before database switching', function () {
    config()->set('public-api.enabled', true);
    $tenant = (new Tenant)->forceFill([
        'id' => 'tenant-unprovisioned',
        'slug' => 'tenant-unprovisioned',
        'status' => 'active',
        'public_web_enabled' => true,
        'provisioning_status' => 'awaiting_payment',
        'provisioned_at' => null,
    ]);
    $domain = (new Domain)->forceFill([
        'id' => 1,
        'tenant_id' => 'tenant-unprovisioned',
        'domain' => 'tenant-unprovisioned.sewantara.id',
        'type' => 'subdomain',
        'status' => 'active',
    ]);
    $request = Request::create('/v1/public/tenant');
    $request->attributes->set('resolved_tenant', $tenant);
    $request->attributes->set('resolved_domain', $domain);
    $continued = false;

    $response = app(ValidatePublicTenantEligibility::class)->handle(
        $request,
        function () use (&$continued) {
            $continued = true;

            return response()->noContent();
        },
    );

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true)['error']['code'])
        ->toBe('TENANT_SERVICE_UNAVAILABLE')
        ->and($continued)->toBeFalse();
});

test('tenant initialization fails closed and always restores central context', function () {
    config()->set('database.connections.central_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('tenancy.database.central_connection', 'central_test');
    config()->set('database.default', 'tenant');
    DB::setDefaultConnection('tenant');

    $tenant = (new Tenant)->forceFill([
        'id' => 'tenant-broken',
        'slug' => 'tenant-broken',
    ]);
    $request = Request::create('/v1/public/tenant');
    $request->attributes->set('resolved_tenant', $tenant);
    $tenancy = app(Tenancy::class);
    $tenancy->getBootstrappersUsing = fn (): array => [];
    $guard = Mockery::mock(TenantSchemaGuard::class);
    $guard->shouldReceive('assertReady')
        ->once()
        ->with($tenant)
        ->andThrow(new RuntimeException('schema missing'));
    $continued = false;
    $middleware = new InitializePublicTenancy($tenancy, $guard);

    $response = $middleware->handle($request, function () use (&$continued) {
        $continued = true;

        return response()->noContent();
    });

    expect($response->getStatusCode())->toBe(503)
        ->and($continued)->toBeFalse()
        ->and($tenancy->initialized)->toBeFalse()
        ->and($tenancy->tenant)->toBeNull()
        ->and(app()->bound('currentTenant'))->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('central_test');
});

test('tenant schema guard rejects PostgreSQL public schema fallback', function () {
    $tenant = (new Tenant)->forceFill(['id' => 'tenant-schema']);
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT current_schema() AS schema_name')
        ->andReturn((object) ['schema_name' => 'public']);
    $connection->shouldNotReceive('table');
    DB::shouldReceive('connection')
        ->once()
        ->with('tenant')
        ->andReturn($connection);

    expect(fn () => app(TenantSchemaGuard::class)->assertReady($tenant))
        ->toThrow(RuntimeException::class, 'Unexpected tenant database schema.');
});

test('tenant schema guard requires a marker owned by the resolved tenant', function () {
    $tenant = (new Tenant)->forceFill(['id' => 'tenant-schema']);
    $query = Mockery::mock();
    $query->shouldReceive('where')
        ->once()
        ->with('tenant_id', 'tenant-schema')
        ->andReturnSelf();
    $query->shouldReceive('exists')->once()->andReturnFalse();
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
    $connection->shouldReceive('table')
        ->once()
        ->with('tenant_business_profiles')
        ->andReturn($query);
    DB::shouldReceive('connection')
        ->once()
        ->with('tenant')
        ->andReturn($connection);

    expect(fn () => app(TenantSchemaGuard::class)->assertReady($tenant))
        ->toThrow(
            RuntimeException::class,
            'Tenant database marker does not match.',
        );
});
