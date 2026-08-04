<?php

use App\Http\Controllers\Api\Public\ArticleIndexController;
use App\Http\Controllers\Api\Public\ArticleShowController;
use App\Http\Controllers\Api\Public\AvailabilityController;
use App\Http\Controllers\Api\Public\BookingPaymentCheckoutController;
use App\Http\Controllers\Api\Public\BookingStoreController;
use App\Http\Controllers\Api\Public\BookingTrackingController;
use App\Http\Controllers\Api\Public\CategoryIndexController;
use App\Http\Controllers\Api\Public\HomeController;
use App\Http\Controllers\Api\Public\InfrastructureHealthController;
use App\Http\Controllers\Api\Public\PaymentShowController;
use App\Http\Controllers\Api\Public\ProductIndexController;
use App\Http\Controllers\Api\Public\ProductShowController;
use App\Http\Controllers\Api\Public\QuoteStoreController;
use App\Http\Controllers\Api\Public\ReadinessController;
use App\Http\Controllers\Api\Public\SitemapController;
use App\Http\Controllers\Api\Public\TenantController;
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
        /*
        |--------------------------------------------------------------------------
        | Tenant and homepage
        |--------------------------------------------------------------------------
        */

        Route::get('/tenant', TenantController::class)
            ->middleware('public.tenant.rate:read')
            ->name('tenant');

        Route::get('/home', HomeController::class)
            ->middleware('public.tenant.rate:read')
            ->name('home');

        /*
        |--------------------------------------------------------------------------
        | Catalog
        |--------------------------------------------------------------------------
        */

        Route::get('/categories', CategoryIndexController::class)
            ->middleware('public.tenant.rate:read')
            ->name('categories.index');

        Route::get('/catalog', ProductIndexController::class)
            ->middleware('public.tenant.rate:read')
            ->name('catalog.index');

        Route::get('/catalog/{product:public_slug}', ProductShowController::class)
            ->middleware('public.tenant.rate:product')
            ->name('catalog.show');

        Route::get(
            '/catalog/{product:public_slug}/availability',
            AvailabilityController::class,
        )
            ->middleware('public.tenant.rate:availability')
            ->name('catalog.availability');

        /*
        |--------------------------------------------------------------------------
        | Quote and booking
        |--------------------------------------------------------------------------
        */

        Route::post('/bookings/quote', QuoteStoreController::class)
            ->middleware('public.tenant.rate:quote')
            ->name('bookings.quote');

        Route::post('/bookings', BookingStoreController::class)
            ->middleware('public.tenant.rate:booking')
            ->name('bookings.store');

        Route::post(
            '/bookings/{bookingCode}/tracking',
            BookingTrackingController::class,
        )
            ->middleware('public.tenant.rate:tracking')
            ->name('bookings.tracking');

        Route::post(
            '/bookings/{bookingCode}/payments/checkout',
            BookingPaymentCheckoutController::class,
        )
            ->middleware('public.tenant.rate:payment')
            ->name('bookings.payments.checkout');

        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/payments/{publicPaymentId}',
            PaymentShowController::class,
        )
            ->middleware('public.tenant.rate:payment')
            ->name('payments.show');

        /*
        |--------------------------------------------------------------------------
        | Blog and SEO
        |--------------------------------------------------------------------------
        */

        Route::get('/blog', ArticleIndexController::class)
            ->middleware('public.tenant.rate:read')
            ->name('blog.index');

        Route::get('/blog/{article:slug}', ArticleShowController::class)
            ->middleware('public.tenant.rate:read')
            ->name('blog.show');

        Route::get('/sitemap', SitemapController::class)
            ->middleware('public.tenant.rate:read')
            ->name('sitemap');
    });
