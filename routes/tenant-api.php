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

Route::prefix('v1/{tenant}')
    ->middleware([
        'tenant.path',
        'tenant.user',
        'tenant.active',
        'tenant.subscription',
    ])
    ->group(function () {
        Route::get('/me', CurrentTenantController::class);

        Route::apiResource('branches', BranchController::class)->only(['index', 'store']);
        Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('product-units', ProductUnitController::class)->only(['index', 'store']);
        Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show', 'update']);
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show']);
        Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store']);
        Route::post('/availability/check', AvailabilityController::class);
        Route::get('/reports/dashboard', DashboardReportController::class);
    });
