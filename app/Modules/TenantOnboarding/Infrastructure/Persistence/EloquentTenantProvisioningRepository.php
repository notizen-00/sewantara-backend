<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Persistence;

use App\Models\BusinessTemplate;
use App\Models\CentralUser;
use App\Models\Domain;
use App\Models\Tenant;
use App\Modules\TenantOnboarding\Application\Data\ProvisionedTenant;
use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;
use App\Modules\TenantOnboarding\Contracts\TenantProvisioningRepository;
use App\Support\TenantHostname;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EloquentTenantProvisioningRepository implements TenantProvisioningRepository
{
    public function provision(RegisterTenantCommand $command): ProvisionedTenant
    {
        $template = BusinessTemplate::query()
            ->where('code', $command->businessType)
            ->where('is_active', true)
            ->firstOrFail();

        $tenant = Tenant::create([
            'name' => $command->businessName,
            'slug' => $this->uniqueTenantSlug($command->businessName),
            'business_type' => $command->businessType,
            'email' => $command->ownerEmail,
            'phone' => $command->ownerPhone,
            'timezone' => config('tenancy.registration_defaults.timezone'),
            'currency' => config('tenancy.registration_defaults.currency'),
            'status' => 'pending',
            'provisioning_status' => 'pending',
        ]);

        $hostname = TenantHostname::fromSubdomain($command->subdomain);

        /** @var Domain $domain */
        $domain = $tenant->createDomain([
            'domain' => $hostname,
            'is_primary' => true,
            'verification_status' => 'verified',
            'verified_at' => now(),
            'ssl_status' => 'pending',
        ]);

        $ownerId = (string) Str::uuid();
        $password = Hash::make($command->ownerPassword);

        CentralUser::query()->create([
            'id' => $ownerId,
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $command->ownerName,
            'email' => $command->ownerEmail,
            'phone' => $command->ownerPhone,
            'password' => $password,
            'is_active' => true,
        ]);

        $tenant->setInternal('pending_owner', [
            'id' => $ownerId,
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $command->ownerName,
            'email' => $command->ownerEmail,
            'phone' => $command->ownerPhone,
            'password' => $password,
            'is_active' => true,
        ]);
        $tenant->setInternal('pending_onboarding', [
            'template_code' => $template->code,
            'template_version' => $template->version,
            'configuration' => $template->configuration,
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
