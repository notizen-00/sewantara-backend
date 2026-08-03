<?php

use App\Models\Tenant;
use App\Modules\TenantAuthentication\Application\ManageTenantAuthentication;
use App\Modules\TenantAuthentication\Application\ResolveTenantFromCentralAccount;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Stancl\Tenancy\Tenancy;

test('central tenant Google auth routes are publicly available', function () {
    $redirectRoute = app('router')->getRoutes()
        ->getByName('central.auth.google.redirect');
    $callbackRoute = app('router')->getRoutes()
        ->getByName('central.auth.google.callback');
    $exchangeRoute = app('router')->getRoutes()
        ->getByName('central.auth.google.exchange');

    expect($redirectRoute)->not->toBeNull()
        ->and($redirectRoute->uri())->toBe('api/central/auth/google/redirect')
        ->and($redirectRoute->methods())->toContain('GET')
        ->and($callbackRoute)->not->toBeNull()
        ->and($callbackRoute->uri())->toBe('api/central/auth/google/callback')
        ->and($callbackRoute->methods())->toContain('GET')
        ->and($exchangeRoute)->not->toBeNull()
        ->and($exchangeRoute->uri())->toBe('api/central/auth/google/exchange')
        ->and($exchangeRoute->methods())->toContain('POST');
});

test('Google redirect starts the OAuth flow and remembers the device name', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://accounts.google.test/oauth'));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $this->get('/api/central/auth/google/redirect?device_name=dashboard-web')
        ->assertRedirect('https://accounts.google.test/oauth');

    expect(session('google_auth_device_name'))->toBe('dashboard-web');
});

test('Google callback returns to Nuxt with a one-time token exchange code', function () {
    config()->set(
        'services.google.frontend_callback',
        'https://app.sewantara.id/auth/google/callback',
    );
    config()->set('services.google.exchange_ttl', 60);

    $googleUser = SocialiteUser::fake([
        'email' => 'owner@example.test',
        'verified_email' => true,
    ]);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => 'tenant-a', 'status' => 'active']);

    $resolver = Mockery::mock(ResolveTenantFromCentralAccount::class);
    $resolver->shouldReceive('execute')
        ->once()
        ->with('owner@example.test')
        ->andReturn($tenant);
    app()->instance(ResolveTenantFromCentralAccount::class, $resolver);

    $authentication = Mockery::mock(ManageTenantAuthentication::class);
    $authentication->shouldReceive('loginWithVerifiedEmail')
        ->once()
        ->with('owner@example.test', 'dashboard-web')
        ->andReturn([
            'access_token' => 'tenant-token',
            'user' => [
                'id' => 10,
                'tenant_id' => 'tenant-a',
                'email' => 'owner@example.test',
            ],
        ]);
    app()->instance(ManageTenantAuthentication::class, $authentication);

    $tenancy = Mockery::mock(Tenancy::class);
    $tenancy->shouldReceive('initialize')->once()->with($tenant);
    $tenancy->shouldReceive('end')->once();
    app()->instance(Tenancy::class, $tenancy);

    $response = $this->withSession([
        'google_auth_device_name' => 'dashboard-web',
    ])->get(
        '/api/central/auth/google/callback?code=google-code&state=valid-state',
    );

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $response->assertRedirectContains(
        'https://app.sewantara.id/auth/google/callback?code=',
    );

    expect($query['code'] ?? null)->toBeString()->toHaveLength(64);

    $this->postJson('/api/central/auth/google/exchange', [
        'code' => $query['code'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.access_token', 'tenant-token')
        ->assertJsonPath('data.user.tenant_id', 'tenant-a');

    $this->postJson('/api/central/auth/google/exchange', [
        'code' => $query['code'],
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'GOOGLE_AUTH_CODE_INVALID');

    expect(app()->bound('currentTenant'))->toBeFalse();
});

test('Google callback rejects an unverified email', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
        'email' => 'owner@example.test',
        'verified_email' => false,
    ]));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $this->get('/api/central/auth/google/callback?code=google-code')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'GOOGLE_EMAIL_UNVERIFIED');
});
