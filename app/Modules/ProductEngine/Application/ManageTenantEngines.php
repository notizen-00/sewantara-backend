<?php

namespace App\Modules\ProductEngine\Application;

use App\Models\Engine;
use App\Models\TenantEngine;
use Illuminate\Validation\ValidationException;

class ManageTenantEngines
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(string $tenantId): array
    {
        $tenantEngines = TenantEngine::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('engine_id');

        return Engine::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Engine $engine): array => $this->present($engine, $tenantEngines->get($engine->getKey())))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(string $tenantId, string $engineCode): array
    {
        $engine = $this->findEngine($engineCode);

        $tenantEngine = TenantEngine::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'engine_id' => $engine->getKey()],
            [
                'is_enabled' => true,
                'enabled_at' => now(),
                'disabled_at' => null,
                'price_snapshot' => $engine->monthly_price,
            ],
        );

        return $this->present($engine, $tenantEngine);
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(string $tenantId, string $engineCode): array
    {
        $engine = $this->findEngine($engineCode);

        if ($engine->is_core) {
            throw ValidationException::withMessages([
                'engine_code' => ['Engine ini merupakan bagian dari paket dasar dan tidak dapat dinonaktifkan.'],
            ]);
        }

        $tenantEngine = TenantEngine::query()->where('tenant_id', $tenantId)
            ->where('engine_id', $engine->getKey())
            ->first();

        if ($tenantEngine === null || ! $tenantEngine->is_enabled) {
            throw ValidationException::withMessages([
                'engine_code' => ['Engine ini belum aktif untuk akun usaha ini.'],
            ]);
        }

        $tenantEngine->forceFill([
            'is_enabled' => false,
            'disabled_at' => now(),
        ])->save();

        return $this->present($engine, $tenantEngine);
    }

    private function findEngine(string $engineCode): Engine
    {
        return Engine::query()->where('code', $engineCode)->where('is_active', true)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Engine $engine, ?TenantEngine $tenantEngine): array
    {
        return [
            'code' => $engine->code,
            'name' => $engine->name,
            'description' => $engine->description,
            'is_core' => $engine->is_core,
            'monthly_price' => number_format((float) $engine->monthly_price, 2, '.', ''),
            'is_enabled' => $tenantEngine?->is_enabled ?? false,
            'price_snapshot' => number_format((float) ($tenantEngine?->price_snapshot ?? 0), 2, '.', ''),
            'enabled_at' => $tenantEngine?->enabled_at?->toIso8601String(),
        ];
    }
}
