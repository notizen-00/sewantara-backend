<?php

namespace App\Modules\Inventory\Application;

use App\Models\Branch;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function create(array $attributes, ?int $actorId = null): ProductUnit
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

    public function transfer(
        ProductUnit $productUnit,
        int $sourceBranchId,
        int $targetBranchId,
        ?int $actorId,
        ?string $notes,
    ): ProductUnit {
        if ((int) $productUnit->branch_id !== $sourceBranchId) {
            throw ValidationException::withMessages([
                'product_unit' => ['Unit tidak berada pada cabang sumber yang sedang aktif.'],
            ]);
        }

        Branch::query()
            ->whereKey($targetBranchId)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use (
            $productUnit,
            $sourceBranchId,
            $targetBranchId,
            $actorId,
            $notes,
        ): ProductUnit {
            $unit = ProductUnit::query()
                ->lockForUpdate()
                ->findOrFail($productUnit->getKey());

            if ($unit->status !== 'available') {
                throw ValidationException::withMessages([
                    'product_unit' => ['Hanya unit berstatus available yang dapat dipindahkan antar cabang.'],
                ]);
            }

            $unit->update(['branch_id' => $targetBranchId]);
            $this->movements->unit(
                $unit->tenant_id,
                $unit,
                'branch_transfer',
                'available',
                'available',
                null,
                $actorId,
                $notes,
                $sourceBranchId,
                $targetBranchId,
            );

            return $unit->refresh()->load('product');
        });
    }
}
