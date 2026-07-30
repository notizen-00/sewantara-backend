<?php

namespace App\Modules\Inventory\Application;

use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ManageProductUnits
{
    public function __construct(
        private readonly RecordInventoryMovement $movements,
    ) {}

    public function paginate(
        ?int $productId,
        ?string $status,
        int $branchId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return ProductUnit::query()
            ->where('branch_id', $branchId)
            ->when($productId, fn ($query, int $value) => $query->where('product_id', $value))
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes, ?string $actorId = null): ProductUnit
    {
        $attributes['status'] ??= 'available';
        $attributes['condition'] ??= 'good';

        return DB::transaction(function () use ($attributes, $actorId): ProductUnit {
            $unit = ProductUnit::query()->create($attributes);
            $this->movements->unit(
                $unit->tenant_id,
                $unit,
                'unit_created',
                null,
                $unit->status,
                null,
                $actorId,
                'Unit persediaan dibuat.',
            );

            return $unit;
        });
    }
}
