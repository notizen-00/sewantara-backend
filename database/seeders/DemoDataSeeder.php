<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoTenantRegistrationSeeder::class,
            TenantOrganizationSeeder::class,
            TenantAccessControlSeeder::class,
            TenantCustomerSeeder::class,
            TenantInventorySeeder::class,
            TenantBookingSeeder::class,
            TenantBillingSeeder::class,
        ]);
    }
}
