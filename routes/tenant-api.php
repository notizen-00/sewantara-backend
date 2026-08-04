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
use App\Http\Controllers\Api\Tenant\PaymentWebhookController;
use App\Http\Controllers\Api\Tenant\ProductController;
use App\Http\Controllers\Api\Tenant\ProductImageController;
use App\Http\Controllers\Api\Tenant\ProductPriceController;
use App\Http\Controllers\Api\Tenant\ProductUnitController;
use App\Http\Controllers\Api\Tenant\PublicArticleController;
use App\Http\Controllers\Api\Tenant\SubscriptionPaymentController;
use App\Http\Controllers\Api\Tenant\TenantAuthController;
use App\Http\Controllers\Api\Tenant\TenantMediaController;
use App\Http\Controllers\Api\Tenant\TenantOnboardingController;
use App\Http\Controllers\Api\Tenant\TenantSettingController;
use Illuminate\Support\Facades\Route;

Route::post('tenant/auth/login', [TenantAuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('tenant.auth.login');

Route::prefix('tenant/{tenant}')
    ->name('tenant.')
    ->middleware('tenant.path')
    ->group(function (): void {

        Route::get('/media/{path}', TenantMediaController::class)
            ->where('path', '.*')
            ->middleware('throttle:240,1')
            ->name('media.show');
        Route::post('/payments/webhooks/{gateway}', PaymentWebhookController::class)
            ->middleware('throttle:120,1')
            ->name('payments.webhooks.handle');

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
                Route::post('/subscription/payments/checkout', [SubscriptionPaymentController::class, 'checkout'])
                    ->middleware('throttle:10,1')
                    ->name('subscription.payments.checkout');
                Route::get('/subscription/payments/{payment}', [SubscriptionPaymentController::class, 'show'])
                    ->whereUuid('payment')
                    ->name('subscription.payments.show');

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

                    Route::get('/settings', [TenantSettingController::class, 'show'])
                        ->name('settings.show');
                    Route::patch('/settings', [TenantSettingController::class, 'update'])
                        ->name('settings.update');
                    Route::post('/settings/images', [TenantSettingController::class, 'updateImages'])
                        ->name('settings.images.update');
                    Route::delete('/settings/images/{image}', [TenantSettingController::class, 'destroyImage'])
                        ->name('settings.images.destroy');


                    Route::apiResource('branches', BranchController::class)->only(['index', 'store', 'update']);
                    Route::post('/branches/{branch}/sync-master-data', [BranchController::class, 'syncMasterData'])
                        ->name('branches.sync-master-data');
                    Route::post('/categories/{category}/image', [CategoryController::class, 'storeImage'])
                        ->name('categories.image.store');
                    Route::delete('/categories/{category}/image', [CategoryController::class, 'destroyImage'])
                        ->name('categories.image.destroy');
                    Route::apiResource('categories', CategoryController::class);
                    Route::post('/products/{product}/images', [ProductImageController::class, 'store'])
                        ->name('products.images.store');
                    Route::patch('/products/{product}/images/{productImage}', [ProductImageController::class, 'update'])
                        ->name('products.images.update');
                    Route::delete('/products/{product}/images/{productImage}', [ProductImageController::class, 'destroy'])
                        ->name('products.images.destroy');
                    Route::apiResource('products', ProductController::class);
                    Route::post('/public-articles/{publicArticle}/cover', [PublicArticleController::class, 'cover'])
                        ->name('public-articles.cover');
                    Route::apiResource('public-articles', PublicArticleController::class)
                        ->parameters(['public-articles' => 'publicArticle']);
                    Route::post('/product-units/{productUnit}/transfer', [ProductUnitController::class, 'transfer'])
                        ->name('product-units.transfer');
                    Route::apiResource('product-units', ProductUnitController::class)->only(['index', 'store']);
                    Route::apiResource('product-prices', ProductPriceController::class)
                        ->only(['index', 'store', 'update', 'destroy']);
                    Route::get('/inventory/stocks', [InventoryStockController::class, 'index'])
                        ->name('inventory.stocks.index');
                    Route::post('/inventory/stocks/adjust', [InventoryStockController::class, 'adjust'])
                        ->name('inventory.stocks.adjust');
                    Route::post('/inventory/stocks/transfer', [InventoryStockController::class, 'transfer'])
                        ->name('inventory.stocks.transfer');
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
                Route::post('/bookings/{booking}/payments/checkout', [PaymentController::class, 'checkout'])
                    ->name('bookings.payments.checkout');
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
