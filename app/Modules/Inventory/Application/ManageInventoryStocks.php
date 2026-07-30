<?php

namespace App\Modules\Inventory\Application;

use App\Models\InventoryStock;
use App\Models\InventoryStockMovement;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageInventoryStocks
{
    public function __construct(
        private readonly RecordInventoryMovement $movements,
    ) {}

    public function paginate(
        ?int $productId,
        ?int $branchId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return InventoryStock::query()
            ->when($productId, fn ($query, int $value) => $query->where('product_id', $value))
            ->when($branchId, fn ($query, int $value) => $query->where('branch_id', $value))
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function adjust(
        string $tenantId,
        ?int $actorId,
        array $attributes,
    ): InventoryStock {
        $product = Product::query()->findOrFail($attributes['product_id']);

        if ($product->inventory_type !== 'quantity') {
            throw ValidationException::withMessages([
                'product_id' => ['Penyesuaian stok hanya berlaku untuk produk yang dikelola berdasarkan jumlah stok.'],
            ]);
        }

        return DB::transaction(function () use ($tenantId, $actorId, $attributes): InventoryStock {
            $stock = InventoryStock::query()
                ->where('product_id', $attributes['product_id'])
                ->where('branch_id', $attributes['branch_id'])
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                $stock = InventoryStock::query()->create([
                    'tenant_id' => $tenantId,
                    'product_id' => $attributes['product_id'],
                    'branch_id' => $attributes['branch_id'],
                ]);
            }

            $before = $stock->quantity_total;
            $after = $before + $attributes['quantity'];
            $unavailable = $stock->quantity_reserved
                + $stock->quantity_rented
                + $stock->quantity_maintenance
                + $stock->quantity_damaged
                + $stock->quantity_lost;

            if ($after < $unavailable || $after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Penyesuaian membuat total stok lebih kecil dari stok yang sedang terpakai.'],
                ]);
            }

            $stock->update(['quantity_total' => $after]);
            $this->movements->stock(
                $tenantId,
                (int) $stock->product_id,
                (int) $stock->branch_id,
                'manual_adjustment',
                (int) $attributes['quantity'],
                $before,
                $after,
                null,
                $actorId,
                InventoryStock::class,
                $stock->getKey(),
                $attributes['notes'] ?? null,
            );

            return $stock->refresh();
        });
    }

    public function stockMovements(
        ?int $productId,
        ?int $branchId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return InventoryStockMovement::query()
            ->when($productId, fn ($query, int $value) => $query->where('product_id', $value))
            ->when($branchId, fn ($query, int $value) => $query->where('branch_id', $value))
            ->latest('occurred_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function unitMovements(
        ?int $productUnitId,
        int $branchId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return ProductMovement::query()
            ->when($productUnitId, fn ($query, int $value) => $query->where('product_unit_id', $value))
            ->where(function ($query) use ($branchId): void {
                $query->where('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId);
            })
            ->latest('occurred_at')
            ->paginate(min(max($perPage, 1), 100));
    }
}
