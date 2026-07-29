<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;
use App\Modules\TenantOnboarding\Application\RegisterTenant;
use App\Modules\TenantOnboarding\Contracts\TenantEnvironmentProvisioner;
use Illuminate\Database\Seeder;

class DemoTenantRegistrationSeeder extends Seeder
{
    public const OWNER_EMAIL = 'owner@demo.localhost';

    public const OWNER_PASSWORD = 'DemoTenant123!';

    public const TENANT_SLUG = 'sewantara-demo-rental';

    public const TENANT_SUBDOMAIN = 'demo-rental';

    public function run(): void
    {
        $tenant = Tenant::withTrashed()
            ->where('slug', self::TENANT_SLUG)
            ->first();

        if ($tenant !== null) {
            if ($tenant->trashed()) {
                $tenant->restore();
            }

            app(TenantEnvironmentProvisioner::class)
                ->provision((string) $tenant->getKey());

            $this->command?->info(
                "Demo tenant {$tenant->name} sudah tersedia.",
            );

            return;
        }

        $planModel = config('laravel-subscriptions.models.plan');
        $plan = $planModel::query()
            ->where('slug', 'growth')
            ->where('is_active', true)
            ->firstOrFail();

        $result = app(RegisterTenant::class)->execute(
            new RegisterTenantCommand(
                businessName: 'Sewantara Demo Rental',
                businessType: 'camera_rental',
                subdomain: self::TENANT_SUBDOMAIN,
                ownerName: 'Demo Owner',
                ownerEmail: self::OWNER_EMAIL,
                ownerPhone: '081234567890',
                ownerPassword: self::OWNER_PASSWORD,
                planId: (int) $plan->getKey(),
                billingInterval: $plan->invoice_interval,
            ),
        );

        $this->command?->info(
            "Demo tenant dibuat: {$result->domain}",
        );
    }
}
