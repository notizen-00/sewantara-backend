<?php

use App\Http\Controllers\Api\Shared\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('shared')->name('shared.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');
});
