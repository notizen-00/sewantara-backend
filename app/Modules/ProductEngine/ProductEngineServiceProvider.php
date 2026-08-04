<?php

namespace App\Modules\ProductEngine;

use App\Modules\ProductEngine\Contracts\TenantEngineGate;
use App\Modules\ProductEngine\Contracts\TenantEngineProvisioner;
use App\Modules\ProductEngine\Infrastructure\Persistence\EloquentTenantEngine;
use Illuminate\Support\ServiceProvider;

class ProductEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantEngineGate::class, EloquentTenantEngine::class);
        $this->app->bind(TenantEngineProvisioner::class, EloquentTenantEngine::class);
    }
}
