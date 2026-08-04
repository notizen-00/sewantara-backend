<?php

namespace App\Modules\ProductEngine\Infrastructure\Persistence;

use App\Models\Engine;
use App\Models\TenantEngine;
use App\Modules\ProductEngine\Contracts\TenantEngineGate;
use App\Modules\ProductEngine\Contracts\TenantEngineProvisioner;

class EloquentTenantEngine implements TenantEngineGate, TenantEngineProvisioner
{
    public function isEnabled(string $tenantId, string $engineCode): bool
    {
        return TenantEngine::query()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->whereHas('engine', fn ($query) => $query
                ->where('code', $engineCode)
                ->where('is_active', true))
            ->exists();
    }

    public function enableDefaults(string $tenantId): void
    {
        Engine::query()
            ->whereIn('code', ['rental', 'booking'])
            ->get()
            ->each(function (Engine $engine) use ($tenantId): void {
                TenantEngine::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'engine_id' => $engine->getKey()],
                    [
                        'is_enabled' => true,
                        'enabled_at' => now(),
                        'disabled_at' => null,
                        'price_snapshot' => 0,
                    ],
                );
            });
    }
}
