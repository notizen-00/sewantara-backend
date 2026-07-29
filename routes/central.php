<?php

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Central\PlanController;
use App\Http\Controllers\Api\Central\MidtransSubscriptionWebhookController;
use App\Http\Controllers\Api\Central\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => [
        'success' => true,
        'message' => 'Sewantara API is running.',
    ]);

    Route::get('/plans', PlanController::class);
    Route::post(
        '/billing/midtrans/webhook',
        MidtransSubscriptionWebhookController::class,
    )->name('billing.midtrans.webhook');
    Route::post('/auth/register', RegisterController::class)
        ->middleware('throttle:5,1')
        ->name('auth.register');
    Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'show']);
});
