<?php

namespace App\Modules\Bookings\Application;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Modules\RentalEngine\Application\RentalEngine;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CheckAvailability
{
    public function __construct(
        private readonly RentalEngine $engine,
    ) {}

    public function execute(string $tenantId, array $criteria): Collection
    {
        $engineCode = Product::query()->findOrFail($criteria['product_id'])->engine_code;

        if (! $this->engine->usesRealtimeAvailability($engineCode)) {
            throw ValidationException::withMessages([
                'availability' => ['Ketersediaan waktu nyata dinonaktifkan untuk usaha ini.'],
            ]);
        }

        $criteria = $this->engine->prepareBooking($engineCode, $criteria);

        return ProductUnit::query()
            ->where('product_id', $criteria['product_id'])
            ->whereIn('status', ['available', 'reserved'])
            ->when($criteria['branch_id'] ?? null, fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->whereNotIn('id', function ($query) use ($criteria, $tenantId): void {
                $query->select('product_unit_id')
                    ->from('booking_unit_allocations')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['reserved', 'checked_out'])
                    ->where('start_at', '<', $criteria['end_at'])
                    ->where('end_at', '>', $criteria['start_at']);
            })
            ->get();
    }
}
