<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Modules\TenantOnboarding\Application\ConfigureTenantOnboarding;
use Illuminate\Database\Seeder;

class DemoTenantGoLiveSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()
            ->where('slug', DemoTenantRegistrationSeeder::TENANT_SLUG)
            ->firstOrFail();

        $tenant->run(function () use ($tenant): void {
            $tenantId = (string) $tenant->getTenantKey();
            $onboarding = app(ConfigureTenantOnboarding::class);

            $onboarding->business($tenantId, [
                'business_name' => 'Sewantara Demo Rental',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'branch_name' => 'Sewantara Demo Rental',
                'operating_hours' => [
                    'monday' => ['open' => '08:00', 'close' => '21:00'],
                    'tuesday' => ['open' => '08:00', 'close' => '21:00'],
                    'wednesday' => ['open' => '08:00', 'close' => '21:00'],
                    'thursday' => ['open' => '08:00', 'close' => '21:00'],
                    'friday' => ['open' => '08:00', 'close' => '21:00'],
                    'saturday' => ['open' => '08:00', 'close' => '21:00'],
                    'sunday' => ['open' => '08:00', 'close' => '18:00'],
                ],
            ]);
            $onboarding->inventoryCompleted($tenantId);
            $onboarding->pricingCompleted($tenantId);
            $onboarding->booking($tenantId, [
                'allow_online_booking' => true,
                'allow_walk_in' => true,
                'enable_waiting_list' => false,
                'allocation_strategy' => 'auto_assign',
                'auto_reminder' => true,
                'auto_cancel_unpaid' => true,
                'auto_cancel_minutes' => 30,
            ]);
            $onboarding->payments($tenantId, [
                [
                    'method' => 'cash',
                    'is_enabled' => true,
                    'configuration' => null,
                ],
                [
                    'method' => 'transfer',
                    'is_enabled' => true,
                    'configuration' => [
                        'bank' => 'BCA',
                        'account_name' => 'Sewantara Demo Rental',
                    ],
                ],
            ]);
            $onboarding->goLive($tenantId);
        });
    }
}
