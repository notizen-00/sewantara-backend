<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class TenantCustomerSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->withinDemoTenant(function (string $tenantId): void {
            $customerId = $this->upsertTenantRow(
                table: 'customers',
                tenantId: $tenantId,
                identity: ['phone' => '081298765432'],
                attributes: [
                    'name' => 'Budi Santoso',
                    'email' => 'budi@example.test',
                    'birth_date' => '1995-05-20',
                    'gender' => 'male',
                    'status' => 'active',
                    'notes' => 'Pelanggan demo dengan histori booking aktif.',
                    'deleted_at' => null,
                ],
            );

            $this->upsertTenantRow(
                table: 'customer_addresses',
                tenantId: $tenantId,
                identity: [
                    'customer_id' => $customerId,
                    'label' => 'Rumah',
                ],
                attributes: [
                    'recipient_name' => 'Budi Santoso',
                    'phone' => '081298765432',
                    'address' => 'Jl. Jawa No. 25',
                    'city' => 'Jember',
                    'province' => 'Jawa Timur',
                    'postal_code' => '68121',
                    'latitude' => null,
                    'longitude' => null,
                    'is_primary' => true,
                ],
            );

            $this->upsertTenantRow(
                table: 'customer_documents',
                tenantId: $tenantId,
                identity: [
                    'customer_id' => $customerId,
                    'document_type' => 'ktp',
                ],
                attributes: [
                    'document_number' => '3509000000000001',
                    'front_path' => 'demo/customers/ktp-front.jpg',
                    'back_path' => null,
                    'expired_at' => null,
                    'is_verified' => true,
                    'verified_by' => null,
                    'verified_at' => now(),
                ],
            );
        });
    }
}
