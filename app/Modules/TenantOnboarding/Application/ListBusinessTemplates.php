<?php

namespace App\Modules\TenantOnboarding\Application;

use App\Modules\TenantOnboarding\Contracts\BusinessTemplateCatalog;

class ListBusinessTemplates
{
    public function __construct(
        private readonly BusinessTemplateCatalog $templates,
    ) {}

    public function execute(): array
    {
        return $this->templates->all();
    }
}
