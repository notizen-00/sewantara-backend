<?php

namespace App\Modules\PublicApi\Read;

use App\Models\BookingUnitAllocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockHold;
use App\Models\TenantBusinessProfile;
use App\Modules\PublicApi\Read\Support\PublicBookingMode;
use App\Modules\PublicApi\Read\Support\PublicBranchResolver;
use Carbon\CarbonImmutable;

final class PublicAvailabilityService
{
    public function __construct(
        private readonly PublicCatalogService $catalog,
        private readonly PublicBranchResolver $branches,
        private readonly PublicBookingMode $bookingMode,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>|null
     */
    public function check(string $productSlug, array $criteria): ?array
    {
        $product = $this->catalog->findBySlug($productSlug);
        $branch = $this->branches->resolve();

        if (! $product instanceof Product || $branch === null) {
            return null;
        }

        $timezone = (string) app('currentTenant')->timezone;
        $quantity = (int) ($criteria['quantity'] ?? 1);
        $mode = $this->bookingMode->resolve($this->catalog->configuration());

        if (isset($criteria['date'])) {
            return $this->slots(
                $product,
                (int) $branch->getKey(),
                (string) $criteria['date'],
                (int) $criteria['duration_minutes'],
                $quantity,
                $timezone,
                $mode,
            );
        }

        $startsAt = CarbonImmutable::parse(
            (string) $criteria['start'],
            $timezone,
        )->startOfDay();
        $endsAt = CarbonImmutable::parse(
            (string) $criteria['end'],
            $timezone,
        )->startOfDay();
        $availableCount = $this->availableCount(
            $product,
            (int) $branch->getKey(),
            $startsAt,
            $endsAt,
        );

        return [
            'product' => $this->productReference($product),
            'booking_mode' => $mode,
            'inventory_type' => $this->bookingMode->inventoryType($product->inventory_type),
            'available' => $availableCount >= $quantity,
            'available_count' => $availableCount,
            'requested_quantity' => $quantity,
            'starts_at' => $startsAt->utc()->toIso8601String(),
            'ends_at' => $endsAt->utc()->toIso8601String(),
            'timezone' => $timezone,
            'indicative' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slots(
        Product $product,
        int $branchId,
        string $date,
        int $durationMinutes,
        int $quantity,
        string $timezone,
        string $mode,
    ): array {
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $profile = TenantBusinessProfile::query()->first();
        $hours = $this->operatingHours($profile?->operating_hours, $day);
        $slots = [];

        if ($hours !== null) {
            [$open, $close] = $hours;
            $cursor = $day->setTimeFromTimeString($open);
            $closesAt = $day->setTimeFromTimeString($close);
            $step = max(
                15,
                (int) ($this->catalog->configuration()?->slot_duration_minutes
                    ?? $durationMinutes),
            );

            while ($cursor->addMinutes($durationMinutes) <= $closesAt) {
                $endsAt = $cursor->addMinutes($durationMinutes);
                $availableCount = $this->availableCount(
                    $product,
                    $branchId,
                    $cursor,
                    $endsAt,
                );
                $slots[] = [
                    'starts_at' => $cursor->utc()->toIso8601String(),
                    'ends_at' => $endsAt->utc()->toIso8601String(),
                    'available' => $availableCount >= $quantity,
                    'available_count' => $availableCount,
                ];
                $cursor = $cursor->addMinutes($step);
            }
        }

        return [
            'product' => $this->productReference($product),
            'booking_mode' => $mode,
            'inventory_type' => $this->bookingMode->inventoryType($product->inventory_type),
            'date' => $day->toDateString(),
            'duration_minutes' => $durationMinutes,
            'requested_quantity' => $quantity,
            'timezone' => $timezone,
            'slots' => $slots,
            'indicative' => true,
        ];
    }

    private function availableCount(
        Product $product,
        int $branchId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): int {
        $held = (int) StockHold::query()
            ->where('product_id', $product->getKey())
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereNull('booking_id')
            ->where('expires_at', '>', now())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->sum('quantity');

        if ($product->inventory_type === 'quantity') {
            $available = InventoryStock::query()
                ->where('product_id', $product->getKey())
                ->where('branch_id', $branchId)
                ->get()
                ->sum(fn (InventoryStock $stock): int => max(0, $stock->quantity_available));

            return max(0, $available - $held);
        }

        if ($product->inventory_type !== 'serialized') {
            return 0;
        }

        $unitIds = ProductUnit::query()
            ->where('product_id', $product->getKey())
            ->where('branch_id', $branchId)
            ->whereIn('status', ['available', 'reserved'])
            ->pluck('id');

        if ($unitIds->isEmpty()) {
            return 0;
        }

        $allocated = BookingUnitAllocation::query()
            ->whereIn('product_unit_id', $unitIds)
            ->whereIn('status', ['reserved', 'checked_out'])
            ->where('start_at', '<', $endsAt)
            ->where('end_at', '>', $startsAt)
            ->distinct()
            ->count('product_unit_id');

        return max(0, $unitIds->count() - $allocated - $held);
    }

    /**
     * @param  array<string, mixed>|null  $operatingHours
     * @return array{0: string, 1: string}|null
     */
    private function operatingHours(?array $operatingHours, CarbonImmutable $day): ?array
    {
        $definition = $operatingHours[strtolower($day->englishDayOfWeek)] ?? null;

        if (! is_array($definition)
            || ! is_string($definition['open'] ?? null)
            || ! is_string($definition['close'] ?? null)
            || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $definition['open']) !== 1
            || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $definition['close']) !== 1
            || $definition['open'] >= $definition['close']) {
            return null;
        }

        return [$definition['open'], $definition['close']];
    }

    /**
     * @return array{id: string|null, slug: string, name: string}
     */
    private function productReference(Product $product): array
    {
        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            'name' => $product->name,
        ];
    }
}
