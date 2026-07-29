<?php

use Illuminate\Routing\Route;

test('all documented API routes use reflectable controllers', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with(
            $route->uri(),
            config('postman.routes.prefix', 'api'),
        ));

    foreach ($routes as $route) {
        expect($route->getAction('uses'))
            ->not->toBeInstanceOf(\Closure::class)
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
