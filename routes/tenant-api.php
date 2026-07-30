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
use App\Http\Controllers\Api\Tenant\ProductPriceController;
use App\Http\Controllers\Api\Tenant\ProductUnitController;
use App\Http\Controllers\Api\Tenant\TenantAuthController;
use App\Http\Controllers\Api\Tenant\TenantOnboardingController;
use Illuminate\Support\Facades\Route;

Route::post('tenant/auth/login', [TenantAuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('tenant.auth.login');

Route::prefix('tenant/{tenant}')
    ->name('tenant.')
    ->middleware('tenant.path')
    ->group(function () {
        Route::middleware([
            'auth:sanctum',
            'tenant.user',
            'tenant.branch',
        ])->group(function () {
            Route::post('/auth/logout', [TenantAuthController::class, 'logout'])
                ->name('auth.logout');

            Route::middleware([
                'tenant.accessible',
            ])->group(function () {
                Route::get('/me', CurrentTenantController::class)->name('me');

                Route::middleware('tenant.subscription')->group(function () {
                    Route::get('/onboarding', [TenantOnboardingController::class, 'show'])
                        ->name('onboarding.show');
                    Route::patch('/onboarding/business', [TenantOnboardingController::class, 'business'])
                        ->name('onboarding.business');
                    Route::patch('/onboarding/rental', [TenantOnboardingController::class, 'rental'])
                        ->name('onboarding.rental');
                    Route::post('/onboarding/inventory/complete', [TenantOnboardingController::class, 'inventory'])
                        ->name('onboarding.inventory');
                    Route::post('/onboarding/pricing/complete', [TenantOnboardingController::class, 'pricing'])
                        ->name('onboarding.pricing');
                    Route::patch('/onboarding/booking', [TenantOnboardingController::class, 'booking'])
                        ->name('onboarding.booking');
                    Route::patch('/onboarding/payments', [TenantOnboardingController::class, 'payments'])
                        ->name('onboarding.payments');
                    Route::post('/onboarding/go-live', [TenantOnboardingController::class, 'goLive'])
                        ->name('onboarding.go-live');

                    Route::apiResource('branches', BranchController::class)->only(['index', 'store']);
                    Route::post('/branches/{branch}/sync-master-data', [BranchController::class, 'syncMasterData'])
                        ->name('branches.sync-master-data');
                    Route::apiResource('categories', CategoryController::class);
                    Route::apiResource('products', ProductController::class);
                    Route::apiResource('product-units', ProductUnitController::class)->only(['index', 'store']);
                    Route::apiResource('product-prices', ProductPriceController::class)
                        ->only(['index', 'store', 'update', 'destroy']);
                    Route::get('/inventory/stocks', [InventoryStockController::class, 'index'])
                        ->name('inventory.stocks.index');
                    Route::post('/inventory/stocks/adjust', [InventoryStockController::class, 'adjust'])
                        ->name('inventory.stocks.adjust');
                });
            });

            Route::middleware([
                'tenant.active',
                'tenant.subscription',
            ])->group(function () {
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
    });
