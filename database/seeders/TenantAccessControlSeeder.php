<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantAccessControlSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->withinDemoTenant(function (string $tenantId): void {
            $roleId = $this->upsertTenantRow(
                table: 'roles',
                tenantId: $tenantId,
                identity: ['code' => 'owner'],
                attributes: [
                    'name' => 'Owner',
                    'is_system' => true,
                ],
            );

            foreach ($this->permissions() as $permission) {
                $permissionId = $this->upsertIncrementingRow(
                    table: 'permissions',
                    identity: ['code' => $permission['code']],
                    attributes: [
                        'name' => $permission['name'],
                        'module' => $permission['module'],
                    ],
                );

                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }

            $ownerId = (string) DB::table('users')
                ->where('email', DemoTenantRegistrationSeeder::OWNER_EMAIL)
                ->value('id');

            DB::table('user_roles')->updateOrInsert([
                'user_id' => $ownerId,
                'role_id' => $roleId,
                'branch_id' => null,
            ]);
        });
    }

    /**
     * @return array<int, array{code: string, name: string, module: string}>
     */
    private function permissions(): array
    {
        return [
            ['code' => 'branches.manage', 'name' => 'Kelola cabang', 'module' => 'organization'],
            ['code' => 'customers.manage', 'name' => 'Kelola pelanggan', 'module' => 'customer'],
            ['code' => 'inventory.manage', 'name' => 'Kelola inventaris', 'module' => 'inventory'],
            ['code' => 'bookings.manage', 'name' => 'Kelola pemesanan', 'module' => 'booking'],
            ['code' => 'payments.manage', 'name' => 'Kelola pembayaran', 'module' => 'billing'],
            ['code' => 'reports.view', 'name' => 'Lihat laporan', 'module' => 'reporting'],
        ];
    }
}
