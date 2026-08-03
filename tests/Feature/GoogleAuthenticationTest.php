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

    expect($redirectRoute)->not->toBeNull()
        ->and($redirectRoute->uri())->toBe('api/central/auth/google/redirect')
        ->and($redirectRoute->methods())->toContain('GET')
        ->and($callbackRoute)->not->toBeNull()
        ->and($callbackRoute->uri())->toBe('api/central/auth/google/callback')
        ->and($callbackRoute->methods())->toContain('GET');
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

test('Google callback issues a tenant token for a verified registered email', function () {
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

    $this->withSession(['google_auth_device_name' => 'dashboard-web'])
        ->get('/api/central/auth/google/callback?code=google-code&state=valid-state')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.access_token', 'tenant-token')
        ->assertJsonPath('data.user.tenant_id', 'tenant-a');

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
