<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InitializeTenantDatabase
{
    /**
     * @param  array<string, mixed>  $owner
     */
    public function handle(
        string $tenantId,
        string $businessName,
        array $owner,
    ): void {
        DB::transaction(function () use ($tenantId, $businessName, $owner): void {
            $user = User::query()->find($owner['id']);

            if ($user === null) {
                $user = User::query()->forceCreate($owner);
            }

            $mainBranch = Branch::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => 'MAIN',
                ],
                [
                    'name' => $businessName,
                    'email' => $owner['email'] ?? null,
                    'phone' => $owner['phone'] ?? null,
                    'is_active' => true,
                ],
            );

            DB::table('branch_users')->updateOrInsert(
                [
                    'branch_id' => $mainBranch->getKey(),
                    'user_id' => $user->getKey(),
                ],
                [
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
