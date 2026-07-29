<?php

namespace App\Modules\TenantOnboarding\Application\Data;

readonly class RegisterTenantCommand
{
    public function __construct(
        public string $businessName,
        public ?string $businessType,
        public string $subdomain,
        public string $ownerName,
        public string $ownerEmail,
        public ?string $ownerPhone,
        public string $ownerPassword,
        public int $planId,
        public string $billingInterval,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            businessName: $data['business_name'],
            businessType: $data['business_type'] ?? null,
            subdomain: $data['subdomain'],
            ownerName: $data['owner']['name'],
            ownerEmail: $data['owner']['email'],
            ownerPhone: $data['owner']['phone'] ?? null,
            ownerPassword: $data['owner']['password'],
            planId: (int) $data['plan_id'],
            billingInterval: $data['billing_interval'],
        );
    }
}
