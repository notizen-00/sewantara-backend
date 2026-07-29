<?php

namespace App\Modules\TenantOnboarding\Application\Data;

use DateTimeImmutable;

readonly class StartedSubscription
{
    public function __construct(
        public string $status,
        public ?DateTimeImmutable $trialEndsAt,
    ) {}
}
