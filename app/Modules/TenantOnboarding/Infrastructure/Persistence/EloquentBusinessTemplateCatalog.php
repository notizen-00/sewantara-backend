<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Persistence;

use App\Models\BusinessTemplate;
use App\Modules\TenantOnboarding\Contracts\BusinessTemplateCatalog;

class EloquentBusinessTemplateCatalog implements BusinessTemplateCatalog
{
    public function all(): array
    {
        return BusinessTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'code',
                'name',
                'description',
                'icon',
                'configuration',
                'version',
            ])
            ->toArray();
    }
}
