<?php

use Illuminate\Support\Facades\Route;

Route::prefix('internal/v1')->group(function () {
    Route::get('/health', fn () => [
        'success' => true,
        'message' => 'Internal API placeholder is ready.',
    ]);
});
