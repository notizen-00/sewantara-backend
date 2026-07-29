<?php

arch('tenant onboarding application layer stays framework independent')
    ->expect('App\Modules\TenantOnboarding\Application')
    ->not->toUse([
        'App\Models',
        'Illuminate',
        'Laravelcm',
        'Midtrans',
        'Stancl',
    ]);

arch('tenant onboarding contracts stay framework independent')
    ->expect('App\Modules\TenantOnboarding\Contracts')
    ->toBeInterfaces()
    ->not->toUse([
        'App\Models',
        'Illuminate',
        'Laravelcm',
        'Midtrans',
        'Stancl',
    ]);
