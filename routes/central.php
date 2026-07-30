<?php

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Central\BusinessTemplateController;
use App\Http\Controllers\Api\Central\MidtransSubscriptionWebhookController;
use App\Http\Controllers\Api\Central\PlanController;
use App\Http\Controllers\Api\Central\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('central')->name('central.')->group(function () {
    Route::get('/business-templates', [BusinessTemplateController::class, 'index'])
        ->name('business-templates.index');
    Route::get('/plans', PlanController::class)->name('plans.index');
    Route::post(
        '/billing/midtrans/webhook',
        MidtransSubscriptionWebhookController::class,
    )->name('billing.midtrans.webhook');
    Route::post('/auth/register', RegisterController::class)
        ->middleware('throttle:5,1')
        ->name('auth.register');
    Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'show']);
});
