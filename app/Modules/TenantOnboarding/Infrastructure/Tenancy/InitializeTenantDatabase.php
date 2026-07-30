<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            DB::table('tenant_business_profiles')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'id' => DB::table('tenant_business_profiles')
                        ->where('tenant_id', $tenantId)
                        ->value('id') ?? (string) Str::uuid(),
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
                    'id' => DB::table('rental_configurations')
                        ->where('tenant_id', $tenantId)
                        ->value('id') ?? (string) Str::uuid(),
                    ...$onboarding['configuration'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('tenant_onboarding')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'id' => DB::table('tenant_onboarding')
                        ->where('tenant_id', $tenantId)
                        ->value('id') ?? (string) Str::uuid(),
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
                    'id' => DB::table('tenant_payment_methods')
                        ->where('tenant_id', $tenantId)
                        ->where('method', 'cash')
                        ->value('id') ?? (string) Str::uuid(),
                    'is_enabled' => true,
                    'configuration' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
