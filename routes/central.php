<?php

use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Central\BusinessTemplateController;
use App\Http\Controllers\Api\Central\DokuSubscriptionWebhookController;
use App\Http\Controllers\Api\Central\MidtransSubscriptionWebhookController;
use App\Http\Controllers\Api\Central\PlanController;
use App\Http\Controllers\Api\Central\TenantController;
use App\Http\Controllers\Api\Central\XenditSubscriptionWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('central')->name('central.')->group(function () {
    Route::get('/business-templates', [BusinessTemplateController::class, 'index'])
        ->name('business-templates.index');
    Route::get('/plans', PlanController::class)->name('plans.index');
    Route::post(
        '/billing/doku/webhook',
        DokuSubscriptionWebhookController::class,
    )->name('billing.doku.webhook');
    Route::post(
        '/billing/midtrans/webhook',
        MidtransSubscriptionWebhookController::class,
    )->name('billing.midtrans.webhook');
    Route::post(
        '/billing/xendit/webhook',
        XenditSubscriptionWebhookController::class,
    )->name('billing.xendit.webhook');
    Route::post('/auth/register', RegisterController::class)
        ->middleware('throttle:5,1')
        ->name('auth.register');
    Route::middleware(['web', 'throttle:20,1'])->group(function (): void {
        Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
            ->name('auth.google.redirect');
        Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
            ->name('auth.google.callback');
    });
    Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'show']);
});
