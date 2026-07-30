<?php

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Requests\Auth\RegisterTenantRequest;
use Database\Seeders\PlanSeeder;
use Illuminate\Validation\Rules\Unique;
use Laravelcm\Subscriptions\Interval;

test('registration only accepts essential onboarding fields', function () {
    $rules = (new RegisterTenantRequest)->rules();

    expect($rules)->toHaveKeys([
        'business_name',
        'business_type',
        'subdomain',
        'owner.name',
        'owner.email',
        'owner.password',
        'plan_id',
        'billing_interval',
        'terms_accepted',
    ])->not->toHaveKeys([
        'slug',
        'timezone',
        'currency',
        'status',
    ]);
});

test('registration defaults are controlled by the backend', function () {
    expect(config('tenancy.registration_defaults'))
        ->timezone->toBe('Asia/Jakarta')
        ->currency->toBe('IDR');
});

test('registration requires a unique central owner email', function () {
    $emailRules = (new RegisterTenantRequest)->rules()['owner.email'];

    expect(collect($emailRules)->contains(
        fn (mixed $rule): bool => $rule instanceof Unique,
    ))->toBeTrue();
});

test('the public registration route is rate limited', function () {
    $route = app('router')->getRoutes()->getByName('central.auth.register');

    expect($route->getActionName())->toBe(RegisterController::class)
        ->and($route->gatherMiddleware())->toContain('throttle:5,1');
});

test('seeded plans provide a trial before the monthly billing period', function () {
    $billing = PlanSeeder::billingDefaults();

    expect($billing)
        ->trial_period->toBe(14)
        ->trial_interval->toBe(Interval::DAY->value)
        ->invoice_period->toBe(1)
        ->invoice_interval->toBe(Interval::MONTH->value)
        ->grace_period->toBe(3);
});
