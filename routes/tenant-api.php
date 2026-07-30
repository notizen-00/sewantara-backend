<?php

use App\Http\Controllers\Api\Tenant\AvailabilityController;
use App\Http\Controllers\Api\Tenant\BookingController;
use App\Http\Controllers\Api\Tenant\BranchController;
use App\Http\Controllers\Api\Tenant\CategoryController;
use App\Http\Controllers\Api\Tenant\CurrentTenantController;
use App\Http\Controllers\Api\Tenant\CustomerController;
use App\Http\Controllers\Api\Tenant\DashboardReportController;
use App\Http\Controllers\Api\Tenant\InventoryMovementController;
use App\Http\Controllers\Api\Tenant\InventoryStockController;
use App\Http\Controllers\Api\Tenant\MaintenanceController;
use App\Http\Controllers\Api\Tenant\PaymentController;
use App\Http\Controllers\Api\Tenant\ProductController;
use App\Http\Controllers\Api\Tenant\ProductUnitController;
use App\Http\Controllers\Api\Tenant\TenantAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/{tenant}')
    ->name('tenant.')
    ->middleware('tenant.path')
    ->group(function () {
        Route::post('/auth/login', [TenantAuthController::class, 'login'])
            ->middleware(['tenant.active', 'throttle:5,1'])
            ->name('auth.login');

        Route::middleware([
            'auth:sanctum',
            'tenant.user',
            'tenant.active',
            'tenant.subscription',
        ])->group(function () {
            Route::post('/auth/logout', [TenantAuthController::class, 'logout'])
                ->name('auth.logout');
            Route::get('/me', CurrentTenantController::class)->name('me');

            Route::apiResource('branches', BranchController::class)->only(['index', 'store']);
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('products', ProductController::class);
            Route::apiResource('product-units', ProductUnitController::class)->only(['index', 'store']);
            Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show', 'update']);
            Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show']);
            Route::post('/bookings/{booking}/check-out', [BookingController::class, 'checkOut'])
                ->name('bookings.check-out');
            Route::post('/bookings/{booking}/return', [BookingController::class, 'return'])
                ->name('bookings.return');
            Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
                ->name('bookings.cancel');
            Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store'])
                ->name('bookings.payments.store');
            Route::get('/inventory/stocks', [InventoryStockController::class, 'index'])
                ->name('inventory.stocks.index');
            Route::post('/inventory/stocks/adjust', [InventoryStockController::class, 'adjust'])
                ->name('inventory.stocks.adjust');
            Route::get('/inventory/movements/stocks', [InventoryMovementController::class, 'stocks'])
                ->name('inventory.movements.stocks');
            Route::get('/inventory/movements/units', [InventoryMovementController::class, 'units'])
                ->name('inventory.movements.units');
            Route::apiResource('maintenance', MaintenanceController::class)->only(['index', 'store', 'show']);
            Route::post('/maintenance/{maintenance}/start', [MaintenanceController::class, 'start'])
                ->name('maintenance.start');
            Route::post('/maintenance/{maintenance}/complete', [MaintenanceController::class, 'complete'])
                ->name('maintenance.complete');
            Route::post('/maintenance/{maintenance}/cancel', [MaintenanceController::class, 'cancel'])
                ->name('maintenance.cancel');
            Route::post('/availability/check', AvailabilityController::class)
                ->name('availability.check');
            Route::get('/reports/dashboard', DashboardReportController::class)
                ->name('reports.dashboard');
        });
    });
