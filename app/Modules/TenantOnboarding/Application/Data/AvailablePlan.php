<?php

namespace App\Modules\TenantOnboarding\Application\Data;

readonly class AvailablePlan
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $invoiceInterval,
    ) {}
}
