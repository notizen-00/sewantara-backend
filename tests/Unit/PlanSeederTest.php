<?php

use Database\Seeders\PlanSeeder;

test('plan definitions match the documented package registry', function () {
    $plans = collect(PlanSeeder::definitions())->keyBy('slug');

    expect($plans->keys()->all())->toBe(['starter', 'growth', 'scale'])
        ->and($plans['starter']['price'])->toBe('199000.00')
        ->and($plans['growth']['price'])->toBe('499000.00')
        ->and($plans['scale']['price'])->toBe('999000.00')
        ->and(featureValues($plans['starter']['features']))->toBe([
            ['slug' => 'branches.limit', 'value' => '1'],
            ['slug' => 'users.limit', 'value' => '3'],
            ['slug' => 'products.limit', 'value' => '100'],
        ])
        ->and(featureValues($plans['growth']['features']))->toBe([
            ['slug' => 'branches.limit', 'value' => '3'],
            ['slug' => 'users.limit', 'value' => '10'],
            ['slug' => 'products.limit', 'value' => '1000'],
        ])
        ->and(featureValues($plans['scale']['features']))->toBe([
            ['slug' => 'branches.limit', 'value' => '10'],
            ['slug' => 'users.limit', 'value' => '50'],
            ['slug' => 'products.limit', 'value' => '5000'],
        ]);
});

test('every plan and feature slug is unique', function () {
    $plans = collect(PlanSeeder::definitions());

    expect($plans->pluck('slug')->duplicates())->toBeEmpty();

    $plans->each(function (array $plan): void {
        expect(collect($plan['features'])->pluck('slug')->duplicates())->toBeEmpty();
    });
});

/**
 * @param  array<int, array<string, mixed>>  $features
 * @return array<int, array{slug: string, value: string}>
 */
function featureValues(array $features): array
{
    return collect($features)
        ->map(fn (array $feature): array => [
            'slug' => $feature['slug'],
            'value' => $feature['value'],
        ])
        ->values()
        ->all();
}
