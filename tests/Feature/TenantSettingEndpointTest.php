<?php

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

    createTenantSettingEndpointTables();

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
    DB::table('tenant_business_profiles')->insert([
        'tenant_id' => 'tenant-a',
        'template_code' => 'custom',
        'template_version' => 1,
        'business_name' => 'Tenant A',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'operating_hours' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('rental_configurations')->insert([
        'tenant_id' => 'tenant-a',
        'rental_model' => 'per_day',
        'booking_strategy' => 'date_range',
        'allocation_strategy' => 'manual',
        'slot_duration_minutes' => null,
        'enable_waiting_list' => false,
        'allow_walk_in' => true,
        'allow_online_booking' => true,
        'allow_extend_booking' => false,
        'realtime_availability' => true,
        'auto_reminder' => true,
        'auto_cancel_unpaid' => false,
        'auto_cancel_minutes' => null,
        'engine_version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->headers = [
        'Authorization' => 'Bearer '.$this->user
            ->createToken('tenant-setting-test', ['tenant:access'])
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

test('tenant can read and update regular branding branch and rental engine settings', function () {
    $this->getJson('/api/tenant/tenant-a/settings', $this->headers)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.regular.business_name', 'Tenant A')
        ->assertJsonPath('data.branch.name', 'Cabang Utama')
        ->assertJsonPath('data.rental_engine.rental_model', 'per_day');

    $this->patchJson('/api/tenant/tenant-a/settings', [
        'regular' => [
            'business_name' => 'Sewantara Jember',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'default_language' => 'id',
        ],
        'branding' => [
            'primary_color' => '#0F766E',
        ],
        'branch' => [
            'name' => 'Cabang Jember',
            'phone' => '0331123456',
        ],
        'rental_engine' => [
            'rental_model' => 'per_hour',
            'booking_strategy' => 'session',
            'allocation_strategy' => 'auto_assign',
            'slot_duration_minutes' => 60,
            'enable_waiting_list' => true,
            'allow_extend_booking' => true,
        ],
    ], $this->headers)->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.regular.business_name', 'Sewantara Jember')
        ->assertJsonPath('data.regular.default_language', 'id')
        ->assertJsonPath('data.branding.primary_color', '#0F766E')
        ->assertJsonPath('data.branch.name', 'Cabang Jember')
        ->assertJsonPath('data.rental_engine.rental_model', 'per_hour')
        ->assertJsonPath('data.rental_engine.slot_duration_minutes', 60);

    expect(DB::table('tenant_business_profiles')->value('business_name'))
        ->toBe('Sewantara Jember')
        ->and(DB::table('branches')->where('id', 1)->value('name'))
        ->toBe('Cabang Jember')
        ->and(DB::table('rental_configurations')->value('allocation_strategy'))
        ->toBe('auto_assign')
        ->and(DB::table('tenant_settings')->where('group', 'branding')->where('key', 'primary_color')->exists())
        ->toBeTrue();
});

test('tenant images are stored privately and old files are removed', function () {
    $response = $this->post('/api/tenant/tenant-a/settings/images', [
        'logo' => UploadedFile::fake()->image('logo.png', 320, 320),
        'branch_logo' => UploadedFile::fake()->image('branch-logo.jpg', 320, 320),
    ], $this->headers);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $logoPath = $response->json('data.branding.logo_path');
    $branchLogoPath = $response->json('data.branch.settings.logo_path');

    expect($logoPath)->toStartWith('branding/')
        ->and($branchLogoPath)->toStartWith('branches/1/');

    Storage::disk('local')->assertExists($logoPath);
    Storage::disk('local')->assertExists($branchLogoPath);

    $this->get('/api/tenant/tenant-a/media/'.$logoPath, $this->headers)
        ->assertOk()
        ->assertHeader('content-disposition', 'inline')
        ->assertHeader('cache-control', 'max-age=3600, private');

    Storage::disk('local')->put('unmanaged/secret.txt', 'private');

    $this->get(
        '/api/tenant/tenant-a/media/unmanaged/secret.txt',
        $this->headers,
    )->assertNotFound();

    $replacement = $this->post('/api/tenant/tenant-a/settings/images', [
        'logo' => UploadedFile::fake()->image('new-logo.webp', 320, 320),
    ], $this->headers)->assertOk();

    $replacementPath = $replacement->json('data.branding.logo_path');

    Storage::disk('local')->assertMissing($logoPath);
    Storage::disk('local')->assertExists($replacementPath);

    $this->delete(
        '/api/tenant/tenant-a/settings/images/logo',
        [],
        $this->headers,
    )->assertOk()
        ->assertJsonMissingPath('data.branding.logo_path');

    Storage::disk('local')->assertMissing($replacementPath);
});

test('private tenant media requires authentication', function () {
    $this->getJson('/api/tenant/tenant-a/media/branding/missing.png')
        ->assertUnauthorized();
});

function createTenantSettingEndpointTables(): void
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
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->text('address')->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
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

    Schema::create('tenant_business_profiles', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->unique();
        $table->string('template_code');
        $table->integer('template_version');
        $table->string('business_name');
        $table->string('timezone');
        $table->string('currency');
        $table->json('operating_hours')->nullable();
        $table->timestamps();
    });

    Schema::create('rental_configurations', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->unique();
        $table->string('rental_model');
        $table->string('booking_strategy');
        $table->string('allocation_strategy');
        $table->integer('slot_duration_minutes')->nullable();
        $table->boolean('enable_waiting_list');
        $table->boolean('allow_walk_in');
        $table->boolean('allow_online_booking');
        $table->boolean('allow_extend_booking');
        $table->boolean('realtime_availability');
        $table->boolean('auto_reminder');
        $table->boolean('auto_cancel_unpaid');
        $table->integer('auto_cancel_minutes')->nullable();
        $table->integer('engine_version');
        $table->timestamps();
    });

    Schema::create('tenant_settings', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id')->index();
        $table->string('group');
        $table->string('key');
        $table->json('value');
        $table->timestamps();
        $table->unique(['tenant_id', 'group', 'key']);
    });
}
