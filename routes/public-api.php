<?php

use App\Http\Controllers\Api\Public\InfrastructureHealthController;
use App\Http\Controllers\Api\Public\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', InfrastructureHealthController::class)
    ->middleware(['request.id', 'force.json'])
    ->name('public.health');

Route::get('/readyz', ReadinessController::class)
    ->middleware(['request.id', 'force.json', 'internal.auth'])
    ->name('internal.readiness');

Route::prefix('v1/public')
    ->name('public.v1.')
    ->middleware([
        'request.id',
        'force.json',
        'bff.auth',
        'public.tenant.headers',
        'public.tenant.resolve',
        'public.tenant.eligible',
        'public.tenant.initialize',
        'public.tenant.locale',
    ])
    ->group(function (): void {
        // Public tenant endpoints are registered below as each domain module is enabled.
    });
