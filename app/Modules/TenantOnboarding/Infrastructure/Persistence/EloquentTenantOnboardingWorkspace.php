<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Persistence;

use App\Models\Branch;
use App\Models\RentalConfiguration;
use App\Models\Tenant;
use App\Models\TenantBusinessProfile;
use App\Models\TenantOnboarding;
use App\Models\TenantPaymentMethod;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use App\Modules\RentalEngine\Domain\RentalModel;
use App\Modules\TenantOnboarding\Contracts\TenantOnboardingWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Tenancy;

class EloquentTenantOnboardingWorkspace implements TenantOnboardingWorkspace
{
    private const STEPS = [
        'business_setup',
        'business_template',
        'rental_configuration',
        'inventory_setup',
        'pricing',
        'booking_configuration',
        'payment_configuration',
        'go_live',
    ];

    public function __construct(
        private readonly Tenancy $tenancy,
    ) {}

    public function snapshot(string $tenantId): array
    {
        $progress = TenantOnboarding::query()->firstOrFail();

        return [
            'status' => $progress->status,
            'current_step' => $progress->current_step,
            'completed_steps' => $progress->completed_steps,
            'profile' => TenantBusinessProfile::query()->firstOrFail(),
            'rental_configuration' => RentalConfiguration::query()->firstOrFail(),
            'payment_methods' => TenantPaymentMethod::query()
                ->orderBy('method')
                ->get()
                ->map(fn (TenantPaymentMethod $method): array => [
                    'method' => $method->method,
                    'is_enabled' => $method->is_enabled,
                    'is_configured' => $method->configuration !== null,
                ])
                ->all(),
            'checklist' => $this->checklist($tenantId, $progress),
        ];
    }

    public function updateBusiness(string $tenantId, array $attributes): array
    {
        DB::transaction(function () use ($attributes): void {
            $profile = TenantBusinessProfile::query()->firstOrFail();
            $profile->update([
                'business_name' => $attributes['business_name'],
                'timezone' => $attributes['timezone'],
                'currency' => strtoupper($attributes['currency']),
                'operating_hours' => $attributes['operating_hours'],
            ]);

            Branch::query()->where('code', 'MAIN')->firstOrFail()->update([
                'name' => $attributes['branch_name'],
            ]);

            $this->markCompleted('business_setup');
        });

        $this->tenant($tenantId)->update([
            'name' => $attributes['business_name'],
            'timezone' => $attributes['timezone'],
            'currency' => strtoupper($attributes['currency']),
        ]);

        return $this->snapshot($tenantId);
    }

    public function updateRentalConfiguration(string $tenantId, array $attributes): array
    {
        $rentalModel = RentalModel::from($attributes['rental_model']);
        $bookingStrategy = BookingStrategy::from($attributes['booking_strategy']);

        if (($bookingStrategy === BookingStrategy::Queue
                && $rentalModel !== RentalModel::PerHour)
            || ($bookingStrategy === BookingStrategy::Session
                && $rentalModel !== RentalModel::Session)) {
            throw ValidationException::withMessages([
                'booking_strategy' => ['Strategi pemesanan tidak sesuai dengan model penyewaan.'],
            ]);
        }

        if ($bookingStrategy !== BookingStrategy::DateRange
            && empty($attributes['slot_duration_minutes'])) {
            throw ValidationException::withMessages([
                'slot_duration_minutes' => ['Durasi waktu wajib diisi untuk strategi antrean atau sesi.'],
            ]);
        }

        DB::transaction(function () use ($attributes): void {
            RentalConfiguration::query()->firstOrFail()->update($attributes);
            $this->markCompleted('rental_configuration');
        });

        return $this->snapshot($tenantId);
    }

    public function updateBookingConfiguration(string $tenantId, array $attributes): array
    {
        DB::transaction(function () use ($attributes): void {
            RentalConfiguration::query()->firstOrFail()->update($attributes);
            $this->markCompleted('booking_configuration');
        });

        return $this->snapshot($tenantId);
    }

    public function updatePaymentConfiguration(string $tenantId, array $methods): array
    {
        DB::transaction(function () use ($tenantId, $methods): void {
            foreach ($methods as $method) {
                TenantPaymentMethod::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'method' => $method['method'],
                    ],
                    [
                        'is_enabled' => $method['is_enabled'],
                        'configuration' => $method['configuration'] ?? null,
                    ],
                );
            }

            if (! TenantPaymentMethod::query()->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages([
                    'methods' => ['Minimal satu metode pembayaran harus aktif.'],
                ]);
            }

            $this->markCompleted('payment_configuration');
        });

        return $this->snapshot($tenantId);
    }

    public function completeInventorySetup(string $tenantId): array
    {
        if (! $this->hasInventory()) {
            throw ValidationException::withMessages([
                'inventory' => ['Buat minimal satu unit atau stok produk sebelum melanjutkan.'],
            ]);
        }

        $this->markCompleted('inventory_setup');

        return $this->snapshot($tenantId);
    }

    public function completePricing(string $tenantId): array
    {
        if (! $this->hasCompatiblePricing()) {
            throw ValidationException::withMessages([
                'pricing' => ['Buat harga aktif yang sesuai dengan model penyewaan sebelum melanjutkan.'],
            ]);
        }

        $this->markCompleted('pricing');

        return $this->snapshot($tenantId);
    }

    public function goLive(string $tenantId): array
    {
        $tenant = $this->tenant($tenantId);
        $progress = TenantOnboarding::query()->firstOrFail();
        $checklist = $this->checklist($tenantId, $progress);
        $incomplete = array_keys(array_filter(
            $checklist,
            fn (bool $completed): bool => ! $completed,
        ));

        if ($incomplete !== []) {
            throw ValidationException::withMessages([
                'checklist' => [
                    'Penyiapan awal belum lengkap: '.implode(', ', $this->checklistLabels($incomplete)).'.',
                ],
            ]);
        }

        DB::transaction(function () use ($progress): void {
            $steps = self::STEPS;
            $progress->update([
                'status' => 'completed',
                'current_step' => 'go_live',
                'completed_steps' => $steps,
                'completed_at' => now(),
            ]);
        });

        $tenant->update([
            'status' => 'active',
            'public_web_enabled' => true,
            'activated_at' => now(),
        ]);

        return $this->snapshot($tenantId);
    }

    private function checklist(
        string $tenantId,
        TenantOnboarding $progress,
    ): array {
        $completed = $progress->completed_steps ?? [];
        $tenant = $this->tenant($tenantId);
        $subscription = $tenant->planSubscription('main');

        return [
            'business' => in_array('business_setup', $completed, true)
                && TenantBusinessProfile::query()
                    ->whereNotNull('operating_hours')
                    ->exists(),
            'template' => in_array('business_template', $completed, true),
            'rental_configuration' => in_array('rental_configuration', $completed, true)
                && RentalConfiguration::query()->exists(),
            'inventory' => in_array('inventory_setup', $completed, true)
                && $this->hasInventory(),
            'pricing' => in_array('pricing', $completed, true)
                && $this->hasCompatiblePricing(),
            'booking' => in_array('booking_configuration', $completed, true),
            'payment' => in_array('payment_configuration', $completed, true)
                && TenantPaymentMethod::query()->where('is_enabled', true)->exists(),
            'branch' => Branch::query()->where('is_active', true)->exists(),
            'subscription' => $subscription !== null && ! $subscription->inactive(),
        ];
    }

    private function markCompleted(string $step): void
    {
        $progress = TenantOnboarding::query()->lockForUpdate()->firstOrFail();
        $completed = collect($progress->completed_steps ?? [])
            ->push($step)
            ->unique()
            ->values()
            ->all();
        $nextStep = collect(self::STEPS)
            ->first(fn (string $candidate): bool => ! in_array($candidate, $completed, true))
            ?? 'go_live';

        $progress->update([
            'status' => 'in_progress',
            'current_step' => $nextStep,
            'completed_steps' => $completed,
        ]);
    }

    private function tenant(string $tenantId): Tenant
    {
        $tenant = $this->tenancy->tenant;

        if (! $tenant instanceof Tenant
            || (string) $tenant->getTenantKey() !== $tenantId) {
            throw ValidationException::withMessages([
                'tenant' => ['Konteks penyiapan awal akun usaha tidak valid.'],
            ]);
        }

        return $tenant;
    }

    private function hasInventory(): bool
    {
        return DB::table('product_units')->whereNull('deleted_at')->exists()
            || DB::table('inventory_stocks')->where('quantity_total', '>', 0)->exists();
    }

    /**
     * @param  array<int, string>  $items
     * @return array<int, string>
     */
    private function checklistLabels(array $items): array
    {
        $labels = [
            'business' => 'informasi usaha',
            'template' => 'jenis usaha',
            'rental_configuration' => 'konfigurasi penyewaan',
            'inventory' => 'persediaan',
            'pricing' => 'harga',
            'booking' => 'pemesanan',
            'payment' => 'pembayaran',
            'branch' => 'cabang',
            'subscription' => 'langganan',
        ];

        return array_map(
            fn (string $item): string => $labels[$item] ?? $item,
            $items,
        );
    }

    private function hasCompatiblePricing(): bool
    {
        $pricingType = RentalConfiguration::query()
            ->firstOrFail()
            ->rental_model
            ->pricingType();

        return DB::table('product_prices')
            ->where('pricing_type', $pricingType)
            ->where('is_active', true)
            ->exists();
    }
}
