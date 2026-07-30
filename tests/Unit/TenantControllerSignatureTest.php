<?php

use App\Http\Controllers\Controller;

test('tenant controllers do not receive the tenant route parameter', function () {
    $controllerPath = app_path('Http/Controllers/Api/Tenant');
    $controllerClasses = collect(glob($controllerPath.'/*Controller.php'))
        ->map(function (string $path): string {
            $class = 'App\\Http\\Controllers\\Api\\Tenant\\'
                .pathinfo($path, PATHINFO_FILENAME);

            expect(is_subclass_of($class, Controller::class))->toBeTrue();

            return $class;
        });

    foreach ($controllerClasses as $controllerClass) {
        $controller = new ReflectionClass($controllerClass);

        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $controllerClass) {
                continue;
            }

            expect(
                collect($method->getParameters())
                    ->pluck('name')
                    ->all(),
                "{$controllerClass}::{$method->getName()} masih menerima parameter tenant.",
            )->not->toContain('tenant');
        }
    }
});
