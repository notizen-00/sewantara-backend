<?php

use App\Http\Controllers\Api\Tenant\AvailabilityController;
use App\Http\Controllers\Api\Tenant\BookingController;
use App\Http\Controllers\Api\Tenant\BranchController;
use App\Http\Controllers\Api\Tenant\CustomerController;
use App\Http\Controllers\Api\Tenant\CurrentTenantController;
use App\Http\Controllers\Api\Tenant\DashboardReportController;
use App\Http\Controllers\Api\Tenant\PaymentController;
use App\Http\Controllers\Api\Tenant\ProductController;
use App\Http\Controllers\Api\Tenant\ProductUnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/{tenant}')
    ->name('tenant.')
    ->middleware([
        'tenant.path',
        'tenant.user',
        'tenant.active',
        'tenant.subscription',
    ])
    ->group(function () {
        Route::get('/me', CurrentTenantController::class)->name('me');

        Route::apiResource('branches', BranchController::class)->only(['index', 'store']);
        Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('product-units', ProductUnitController::class)->only(['index', 'store']);
        Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show']);
        Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store'])
            ->name('bookings.payments.store');
        Route::post('/availability/check', AvailabilityController::class)
            ->name('availability.check');
        Route::get('/reports/dashboard', DashboardReportController::class)
            ->name('reports.dashboard');
    });
