<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;

test('all documented API routes use reflectable controllers', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with(
            $route->uri(),
            config('postman.routes.prefix', 'api'),
        ));

    foreach ($routes as $route) {
        expect($route->getAction('uses'))
            ->not->toBeInstanceOf(Closure::class)
            ->and($route->getControllerClass())
            ->not->toBeNull()
            ->and(class_exists($route->getControllerClass()))
            ->toBeTrue();
    }
});

test('API endpoints are grouped without URL versioning', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn (Route $route): string => $route->uri())
        ->filter(fn (string $uri): bool => str_starts_with($uri, 'api/'));

    foreach ($uris as $uri) {
        expect($uri)
            ->toMatch('#^api/(central|tenant|shared)(?:/|$)#')
            ->not->toMatch('#/(?:v|version)\d+(?:/|$)#i');
    }
});

test('generated collection provides reusable tenant and branch variables', function () {
    $outputPath = storage_path('framework/testing/postman');
    config()->set('postman.output.path', $outputPath);

    Artisan::call('postman:generate');

    $collection = json_decode(
        file_get_contents($outputPath.'/api_collection.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $variables = collect($collection['variable'])->keyBy('key');
    $serialized = json_encode($collection, JSON_THROW_ON_ERROR);

    expect($variables)
        ->toHaveKeys(['base_url', 'auth_token', 'tenant', 'x_branch_id'])
        ->and($variables['x_branch_id']['value'])->toBe('1')
        ->and($serialized)
        ->toContain('{{tenant}}')
        ->toContain('{{x_branch_id}}')
        ->toContain('pm.request.headers.upsert')
        ->toContain("pm.collectionVariables.set('tenant'")
        ->not->toContain(':tenant');
});
