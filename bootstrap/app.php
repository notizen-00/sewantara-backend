<?php

use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\InitializeTenantByPath;
use App\Modules\TenantOnboarding\Application\Exceptions\BillingIntervalUnavailable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.path' => InitializeTenantByPath::class,
            'tenant.user' => EnsureUserBelongsToTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.subscription' => EnsureTenantSubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(
            fn (BillingIntervalUnavailable $exception, Request $request) => response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BILLING_INTERVAL_UNAVAILABLE',
                    'message' => $exception->getMessage(),
                    'details' => [
                        'billing_interval' => [$exception->getMessage()],
                    ],
                ],
            ], 422),
        );

        $exceptions->render(
            fn (TenantCouldNotBeIdentifiedOnDomainException $exception, Request $request) => response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOMAIN_NOT_FOUND',
                    'message' => 'Domain ini tidak terdaftar.',
                    'details' => null,
                ],
            ], 404),
        );

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
