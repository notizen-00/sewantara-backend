<?php

namespace App\Modules\Inventory\Application;

use App\Models\Branch;
use App\Models\InventoryStock;
use App\Models\InventoryStockMovement;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Modules\Inventory\Domain\StockAdjustmentReason;
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
            $stock = $this->findOrCreateStock(
                $tenantId,
                (int) $attributes['product_id'],
                (int) $attributes['branch_id'],
            );
            $reason = StockAdjustmentReason::from($attributes['reason_type']);
            $quantity = (int) $attributes['quantity'];
            $availableBefore = $stock->quantity_available;

            $this->applyAdjustment($stock, $reason, $quantity);
            $stock->save();
            $availableAfter = $stock->quantity_available;

            $this->movements->stock(
                $tenantId,
                (int) $stock->product_id,
                (int) $stock->branch_id,
                $reason->movementType(),
                $reason->movementQuantity($quantity),
                $availableBefore,
                $availableAfter,
                null,
                $actorId,
                InventoryStock::class,
                $stock->getKey(),
                $attributes['notes'] ?? null,
            );

            return $stock->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function transfer(
        string $tenantId,
        int $sourceBranchId,
        ?int $actorId,
        array $attributes,
    ): array {
        $product = Product::query()->findOrFail($attributes['product_id']);

        if ($product->inventory_type !== 'quantity') {
            throw ValidationException::withMessages([
                'product_id' => ['Transfer stok quantity hanya berlaku untuk produk yang dikelola berdasarkan jumlah stok.'],
            ]);
        }

        $targetBranch = Branch::query()
            ->whereKey($attributes['target_branch_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($sourceBranchId === (int) $targetBranch->getKey()) {
            throw ValidationException::withMessages([
                'target_branch_id' => ['Cabang tujuan harus berbeda dari cabang sumber.'],
            ]);
        }

        return DB::transaction(function () use (
            $tenantId,
            $sourceBranchId,
            $targetBranch,
            $actorId,
            $attributes,
        ): array {
            $productId = (int) $attributes['product_id'];
            $targetBranchId = (int) $targetBranch->getKey();
            $quantity = (int) $attributes['quantity'];

            InventoryStock::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'branch_id' => $targetBranchId,
            ]);

            $stocks = InventoryStock::query()
                ->where('product_id', $productId)
                ->whereIn('branch_id', [$sourceBranchId, $targetBranchId])
                ->orderBy('branch_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('branch_id');

            $source = $stocks->get($sourceBranchId);
            $target = $stocks->get($targetBranchId);

            if (! $source instanceof InventoryStock || ! $target instanceof InventoryStock) {
                throw ValidationException::withMessages([
                    'product_id' => ['Struktur stok cabang sumber belum tersedia.'],
                ]);
            }

            if ($source->quantity_available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stok tersedia pada cabang sumber tidak mencukupi untuk transfer.'],
                ]);
            }

            $sourceBefore = $source->quantity_available;
            $targetBefore = $target->quantity_available;
            $source->decrement('quantity_total', $quantity);
            $target->increment('quantity_total', $quantity);

            $transfer = InventoryTransfer::query()->create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'from_branch_id' => $sourceBranchId,
                'to_branch_id' => $targetBranchId,
                'quantity' => $quantity,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actorId,
                'occurred_at' => now(),
            ]);

            $this->recordTransferMovements(
                $tenantId,
                $source,
                $target,
                $transfer,
                $quantity,
                $sourceBefore,
                $targetBefore,
                $actorId,
            );

            return [
                'transfer' => $transfer->load(['product', 'fromBranch', 'toBranch']),
                'source_stock' => $source->refresh()->load('branch'),
                'target_stock' => $target->refresh()->load('branch'),
            ];
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

    private function findOrCreateStock(
        string $tenantId,
        int $productId,
        int $branchId,
    ): InventoryStock {
        InventoryStock::query()->firstOrCreate([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'branch_id' => $branchId,
        ]);

        return InventoryStock::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function applyAdjustment(
        InventoryStock $stock,
        StockAdjustmentReason $reason,
        int $quantity,
    ): void {
        match ($reason) {
            StockAdjustmentReason::InitialStock,
            StockAdjustmentReason::Purchase,
            StockAdjustmentReason::CorrectionIn,
            StockAdjustmentReason::OtherIn => $stock->quantity_total += $quantity,

            StockAdjustmentReason::CorrectionOut,
            StockAdjustmentReason::OtherOut => $this->decreaseAvailableTotal($stock, $quantity),

            StockAdjustmentReason::Damaged => $this->moveAvailableToBucket(
                $stock,
                'quantity_damaged',
                $quantity,
            ),
            StockAdjustmentReason::Lost => $this->moveAvailableToBucket(
                $stock,
                'quantity_lost',
                $quantity,
            ),
            StockAdjustmentReason::DamagedRecovered => $this->recoverBucket(
                $stock,
                'quantity_damaged',
                $quantity,
            ),
            StockAdjustmentReason::LostRecovered => $this->recoverBucket(
                $stock,
                'quantity_lost',
                $quantity,
            ),
            StockAdjustmentReason::DamagedDisposed => $this->removeBucketFromTotal(
                $stock,
                'quantity_damaged',
                $quantity,
            ),
            StockAdjustmentReason::LostWriteOff => $this->removeBucketFromTotal(
                $stock,
                'quantity_lost',
                $quantity,
            ),
        };
    }

    private function decreaseAvailableTotal(InventoryStock $stock, int $quantity): void
    {
        if ($stock->quantity_available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Stok tersedia tidak mencukupi untuk pengurangan ini.'],
            ]);
        }

        $stock->quantity_total -= $quantity;
    }

    private function moveAvailableToBucket(
        InventoryStock $stock,
        string $bucket,
        int $quantity,
    ): void {
        if ($stock->quantity_available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Stok tersedia tidak mencukupi untuk perubahan kondisi ini.'],
            ]);
        }

        $stock->{$bucket} += $quantity;
    }

    private function recoverBucket(
        InventoryStock $stock,
        string $bucket,
        int $quantity,
    ): void {
        if ($stock->{$bucket} < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Jumlah pemulihan melebihi stok pada kondisi tersebut.'],
            ]);
        }

        $stock->{$bucket} -= $quantity;
    }

    private function removeBucketFromTotal(
        InventoryStock $stock,
        string $bucket,
        int $quantity,
    ): void {
        if ($stock->{$bucket} < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Jumlah penghapusan melebihi stok pada kondisi tersebut.'],
            ]);
        }

        $stock->{$bucket} -= $quantity;
        $stock->quantity_total -= $quantity;
    }

    private function recordTransferMovements(
        string $tenantId,
        InventoryStock $source,
        InventoryStock $target,
        InventoryTransfer $transfer,
        int $quantity,
        int $sourceBefore,
        int $targetBefore,
        ?int $actorId,
    ): void {
        $this->movements->stock(
            $tenantId,
            (int) $source->product_id,
            (int) $source->branch_id,
            'transfer_out',
            -$quantity,
            $sourceBefore,
            $sourceBefore - $quantity,
            null,
            $actorId,
            InventoryTransfer::class,
            $transfer->getKey(),
            $transfer->notes,
        );
        $this->movements->stock(
            $tenantId,
            (int) $target->product_id,
            (int) $target->branch_id,
            'transfer_in',
            $quantity,
            $targetBefore,
            $targetBefore + $quantity,
            null,
            $actorId,
            InventoryTransfer::class,
            $transfer->getKey(),
            $transfer->notes,
        );
    }
}
