<?php

namespace App\Modules\TenantOnboarding\Application\Data;

use DateTimeImmutable;

readonly class TenantRegistrationResult
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
        public string $planSlug,
        public string $subscriptionStatus,
        public ?DateTimeImmutable $trialEndsAt,
    ) {}
}
