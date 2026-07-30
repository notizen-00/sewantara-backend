<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Arr;
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
        array $onboarding,
        string $timezone,
        string $currency,
    ): void {
        DB::transaction(function () use (
            $tenantId,
            $businessName,
            $owner,
            $onboarding,
            $timezone,
            $currency,
        ): void {
            $user = User::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($owner['email'])])
                ->first();

            if ($user === null) {
                $user = User::query()->forceCreate(
                    Arr::except($owner, ['id']),
                );
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

            DB::table('tenant_business_profiles')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'template_code' => $onboarding['template_code'],
                    'template_version' => $onboarding['template_version'],
                    'business_name' => $businessName,
                    'timezone' => $timezone,
                    'currency' => $currency,
                    'operating_hours' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('rental_configurations')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    ...$onboarding['configuration'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('tenant_onboarding')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'status' => 'in_progress',
                    'current_step' => 'inventory_setup',
                    'completed_steps' => json_encode([
                        'business_setup',
                        'business_template',
                        'rental_configuration',
                    ], JSON_THROW_ON_ERROR),
                    'completed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('tenant_payment_methods')->updateOrInsert(
                ['tenant_id' => $tenantId, 'method' => 'cash'],
                [
                    'is_enabled' => true,
                    'configuration' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
