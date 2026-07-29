<?php

use App\Models\Tenant;
use App\Modules\TenantOnboarding\Contracts\TenantEnvironmentProvisioner;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('registration stores the central owner and complete tenant hostname', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('tenancy.database.central_connection', 'sqlite');
    config()->set('tenancy.tenant_base_domain', 'localhost');
    config()->set('app.url', 'http://localhost');

    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Artisan::call('migrate:fresh', [
        '--database' => 'sqlite',
        '--path' => database_path('migrations/central'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $this->seed(PlanSeeder::class);

    $planId = DB::table(config('laravel-subscriptions.tables.plans'))
        ->where('slug', 'starter')
        ->value('id');

    $environment = Mockery::mock(TenantEnvironmentProvisioner::class);
    $environment->shouldReceive('provision')->once();
    app()->instance(TenantEnvironmentProvisioner::class, $environment);

    $payload = [
        'business_name' => 'Kendo Kenceng',
        'business_type' => 'camera_rental',
        'subdomain' => 'kendokenceng',
        'owner' => [
            'name' => 'Owner Kendo',
            'email' => 'owner.kendo@gmail.com',
            'phone' => '081234567890',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ],
        'plan_id' => $planId,
        'billing_interval' => 'month',
        'terms_accepted' => true,
    ];

    $response = $this->postJson('/api/central/auth/register', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.domain.domain', 'kendokenceng.localhost')
        ->assertJsonPath('data.domain.url', 'http://kendokenceng.localhost');

    $this->assertDatabaseHas('users', [
        'email' => 'owner.kendo@gmail.com',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('domains', [
        'domain' => 'kendokenceng.localhost',
        'is_primary' => true,
    ]);

    $centralPassword = DB::table('users')
        ->where('email', 'owner.kendo@gmail.com')
        ->value('password');
    $pendingOwner = Tenant::query()->firstOrFail()->getInternal('pending_owner');

    expect($pendingOwner['password'])->toBe($centralPassword);

    $payload['business_name'] = 'Kendo Kenceng Lain';
    $payload['owner']['email'] = 'other.owner@gmail.com';

    $this->postJson('/api/central/auth/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subdomain');
});
