<?php

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Requests\Auth\RegisterTenantRequest;
use Database\Seeders\PlanSeeder;
use Laravelcm\Subscriptions\Interval;

test('registration accepts all documented onboarding fields', function () {
    $rules = (new RegisterTenantRequest)->rules();

    expect($rules)->toHaveKeys([
        'business_name',
        'subdomain',
        'owner.name',
        'owner.email',
        'owner.password',
        'plan_id',
        'billing_interval',
        'timezone',
        'currency',
        'terms_accepted',
    ]);
});

test('the public registration route is rate limited', function () {
    $route = app('router')->getRoutes()->getByName('auth.register');

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
