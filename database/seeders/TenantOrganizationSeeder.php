<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantOrganizationSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->withinDemoTenant(function (string $tenantId): void {
            $this->upsertTenantRow(
                table: 'branches',
                tenantId: $tenantId,
                identity: ['code' => 'JBR-01'],
                attributes: [
                    'name' => 'Cabang Jember',
                    'email' => 'jember@demo.localhost',
                    'phone' => '0331123456',
                    'address' => 'Jl. Hayam Wuruk No. 10, Jember',
                    'latitude' => -8.1844866,
                    'longitude' => 113.6680747,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );

            foreach ([
                ['group' => 'business', 'key' => 'business_name', 'value' => 'Sewantara Demo Rental'],
                ['group' => 'business', 'key' => 'timezone', 'value' => 'Asia/Jakarta'],
                ['group' => 'business', 'key' => 'currency', 'value' => 'IDR'],
                ['group' => 'booking', 'key' => 'default_duration', 'value' => 1],
            ] as $setting) {
                $this->upsertTenantRow(
                    table: 'tenant_settings',
                    tenantId: $tenantId,
                    identity: [
                        'group' => $setting['group'],
                        'key' => $setting['key'],
                    ],
                    attributes: [
                        'value' => json_encode(
                            $setting['value'],
                            JSON_THROW_ON_ERROR,
                        ),
                    ],
                );
            }

            $branchId = $this->tenantRowId(
                'branches',
                $tenantId,
                'code',
                'JBR-01',
            );
            $ownerId = (int) DB::table('users')
                ->where('email', DemoTenantRegistrationSeeder::OWNER_EMAIL)
                ->value('id');

            DB::table('branch_users')->updateOrInsert(
                ['branch_id' => $branchId, 'user_id' => $ownerId],
                [
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
