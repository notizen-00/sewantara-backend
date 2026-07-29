<?php

namespace App\Modules\TenantOnboarding\Application\Data;

readonly class ProvisionedTenant
{
    public function __construct(
        public string $tenantId,
        public string $tenantName,
        public string $tenantSlug,
        public string $tenantStatus,
        public string $timezone,
        public string $currency,
        public string $domain,
        public string $ownerId,
        public string $ownerName,
        public string $ownerEmail,
    ) {}
}
