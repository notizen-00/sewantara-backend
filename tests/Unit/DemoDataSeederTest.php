<?php

use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoTenantGoLiveSeeder;
use Database\Seeders\DemoTenantRegistrationSeeder;
use Database\Seeders\TenantAccessControlSeeder;
use Database\Seeders\TenantBillingSeeder;
use Database\Seeders\TenantBookingSeeder;
use Database\Seeders\TenantCustomerSeeder;
use Database\Seeders\TenantInventorySeeder;
use Database\Seeders\TenantOrganizationSeeder;

test('demo data seeder runs feature seeders in dependency order', function () {
    $seeder = new class extends DemoDataSeeder
    {
        /** @var array<int, class-string> */
        public array $featureSeeders = [];

        public function call(
            $class,
            $silent = false,
            array $parameters = [],
        ): static {
            $this->featureSeeders = (array) $class;

            return $this;
        }
    };

    $seeder->run();

    expect($seeder->featureSeeders)->toBe([
        DemoTenantRegistrationSeeder::class,
        TenantOrganizationSeeder::class,
        TenantAccessControlSeeder::class,
        TenantCustomerSeeder::class,
        TenantInventorySeeder::class,
        TenantBookingSeeder::class,
        TenantBillingSeeder::class,
        DemoTenantGoLiveSeeder::class,
    ]);
});

test('demo tenant credentials satisfy registration requirements', function () {
    expect(DemoTenantRegistrationSeeder::OWNER_EMAIL)
        ->toBe('owner@demo.localhost')
        ->and(DemoTenantRegistrationSeeder::OWNER_PASSWORD)
        ->toMatch('/[a-z]/')
        ->toMatch('/[A-Z]/')
        ->toMatch('/[0-9]/')
        ->toMatch('/[^a-zA-Z0-9]/');
});
