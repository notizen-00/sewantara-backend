<?php

use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', fn () => response()->json([
            'success' => true,
            'data' => [
                'service' => 'Sewantara API',
                'status' => 'running',
                'health' => url('/api/v1/health'),
            ],
        ]));
    });
}
