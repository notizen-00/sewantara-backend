<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Tenancy;

use App\Models\Branch;
use App\Models\User;
use App\Modules\ProductEngine\Domain\EngineCode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

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

            $branchAttributes = [
                'name' => $businessName,
                'email' => $owner['email'] ?? null,
                'phone' => $owner['phone'] ?? null,
                'is_active' => true,
            ];

            if (Schema::hasColumns('branches', ['is_public', 'is_primary'])) {
                $branchAttributes['is_public'] = true;
                $branchAttributes['is_primary'] = true;
            }

            $mainBranch = Branch::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => 'MAIN',
                ],
                $branchAttributes,
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

            $primaryEngineCode = EngineCode::fromRentalConfiguration(
                $onboarding['configuration']['booking_strategy'],
            );
            $secondaryEngineCode = $primaryEngineCode === EngineCode::Rental
                ? EngineCode::Booking
                : EngineCode::Rental;

            DB::table('tenant_business_profiles')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'primary_engine_code' => $primaryEngineCode->value,
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
                ['tenant_id' => $tenantId, 'engine_code' => $primaryEngineCode->value],
                [
                    ...$onboarding['configuration'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('rental_configurations')->updateOrInsert(
                ['tenant_id' => $tenantId, 'engine_code' => $secondaryEngineCode->value],
                [
                    ...self::defaultConfigurationFor($secondaryEngineCode),
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

    /**
     * Sane default configuration for whichever of Rental/Booking was not
     * chosen by the tenant's business template, so both engines are usable
     * as soon as the tenant enables the other one.
     *
     * @return array<string, mixed>
     */
    private static function defaultConfigurationFor(EngineCode $engineCode): array
    {
        return match ($engineCode) {
            EngineCode::Rental => [
                'rental_model' => 'per_day',
                'booking_strategy' => 'date_range',
                'allocation_strategy' => 'manual',
                'slot_duration_minutes' => null,
                'enable_waiting_list' => false,
                'allow_walk_in' => true,
                'allow_online_booking' => true,
                'allow_extend_booking' => false,
                'realtime_availability' => true,
                'auto_reminder' => true,
                'auto_cancel_unpaid' => false,
                'auto_cancel_minutes' => null,
                'engine_version' => 1,
            ],
            EngineCode::Booking => [
                'rental_model' => 'session',
                'booking_strategy' => 'session',
                'allocation_strategy' => 'manual',
                'slot_duration_minutes' => 60,
                'enable_waiting_list' => false,
                'allow_walk_in' => true,
                'allow_online_booking' => true,
                'allow_extend_booking' => false,
                'realtime_availability' => true,
                'auto_reminder' => true,
                'auto_cancel_unpaid' => false,
                'auto_cancel_minutes' => null,
                'engine_version' => 1,
            ],
            default => throw new InvalidArgumentException("Tidak ada konfigurasi default untuk engine {$engineCode->value}."),
        };
    }
}
