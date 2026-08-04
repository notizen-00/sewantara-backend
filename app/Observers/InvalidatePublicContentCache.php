<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Modules\PublicApi\Read\Support\PublicContentCache;
use Illuminate\Database\Eloquent\Model;

class InvalidatePublicContentCache
{
    public function __construct(
        private readonly PublicContentCache $cache,
    ) {}

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $tenantId = $model instanceof Tenant
            ? (string) $model->getTenantKey()
            : (string) $model->getAttribute('tenant_id');

        if ($tenantId !== '') {
            $this->cache->invalidate($tenantId);
        }
    }
}
