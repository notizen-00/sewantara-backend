<?php

test('all application controllers remain in the API HTTP layer', function () {
    $actions = collect(app('router')->getRoutes()->getRoutes())
        ->map->getActionName()
        ->filter(fn (string $action): bool => str_starts_with($action, 'App\\'))
        ->values();

    expect($actions)->not->toBeEmpty();

    $actions->each(function (string $action): void {
        expect($action)->toStartWith('App\\Http\\Controllers\\Api\\');
    });
});

test('api controllers delegate persistence and business operations to modules', function () {
    $controllerFiles = glob(app_path('Http/Controllers/Api/**/*.php'));

    expect($controllerFiles)->not->toBeEmpty();

    foreach ($controllerFiles as $controllerFile) {
        $source = file_get_contents($controllerFile);

        expect($source)
            ->not->toContain('::query(')
            ->not->toContain('::create(')
            ->not->toContain('DB::transaction(')
            ->not->toContain('DB::table(');
    }
});
