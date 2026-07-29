<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Persistence;

use App\Models\Domain;
use App\Models\Tenant;
use App\Modules\TenantOnboarding\Application\Data\ProvisionedTenant;
use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;
use App\Modules\TenantOnboarding\Contracts\TenantProvisioningRepository;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class EloquentTenantProvisioningRepository implements TenantProvisioningRepository
{
    public function provision(RegisterTenantCommand $command): ProvisionedTenant
    {
        $tenant = Tenant::create([
            'name' => $command->businessName,
            'slug' => $this->uniqueTenantSlug($command->businessName),
            'business_type' => $command->businessType,
            'email' => $command->ownerEmail,
            'phone' => $command->ownerPhone,
            'timezone' => $command->timezone,
            'currency' => $command->currency,
            'status' => 'pending',
            'provisioning_status' => 'awaiting_payment',
        ]);

        /** @var Domain $domain */
        $domain = $tenant->createDomain([
            'domain' => $command->subdomain,
            'is_primary' => true,
            'verification_status' => 'verified',
            'verified_at' => now(),
            'ssl_status' => 'pending',
        ]);

        $ownerId = (string) Str::uuid();
        $tenant->setInternal('pending_owner', [
            'id' => $ownerId,
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $command->ownerName,
            'email' => $command->ownerEmail,
            'phone' => $command->ownerPhone,
            'password' => Hash::make($command->ownerPassword),
            'is_active' => true,
        ]);
        $tenant->save();

        return new ProvisionedTenant(
            tenantId: (string) $tenant->getTenantKey(),
            tenantName: $tenant->name,
            tenantSlug: $tenant->slug,
            tenantStatus: $tenant->status,
            timezone: $tenant->timezone,
            currency: $tenant->currency,
            domain: $domain->domain,
            ownerId: $ownerId,
            ownerName: $command->ownerName,
            ownerEmail: $command->ownerEmail,
        );
    }

    private function uniqueTenantSlug(string $businessName): string
    {
        $baseSlug = Str::slug($businessName);
        $slug = $baseSlug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}
