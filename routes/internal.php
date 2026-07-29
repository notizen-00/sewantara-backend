<?php

use App\Http\Controllers\Api\Internal\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/v1')->group(function () {
    Route::get('/health', HealthController::class);
});
