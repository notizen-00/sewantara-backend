<?php

namespace App\Modules\Bookings\Application;

use App\Models\ProductUnit;
use Illuminate\Support\Collection;

class CheckAvailability
{
    public function execute(string $tenantId, array $criteria): Collection
    {
        return ProductUnit::query()
            ->where('product_id', $criteria['product_id'])
            ->where('status', 'available')
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
