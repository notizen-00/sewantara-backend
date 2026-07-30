<?php

namespace App\Modules\Inventory\Application;

use App\Models\InventoryStockMovement;
use App\Models\ProductMovement;
use App\Models\ProductUnit;

class RecordInventoryMovement
{
    public function unit(
        string $tenantId,
        ProductUnit $unit,
        string $type,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $bookingId,
        ?int $createdBy,
        ?string $notes = null,
        ?int $fromBranchId = null,
        ?int $toBranchId = null,
    ): ProductMovement {
        return ProductMovement::query()->create([
            'tenant_id' => $tenantId,
            'product_unit_id' => $unit->getKey(),
            'booking_id' => $bookingId,
            'type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_branch_id' => $fromBranchId ?? $unit->branch_id,
            'to_branch_id' => $toBranchId ?? $unit->branch_id,
            'notes' => $notes,
            'occurred_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    public function stock(
        string $tenantId,
        int $productId,
        int $branchId,
        string $type,
        int $quantity,
        int $balanceBefore,
        int $balanceAfter,
        ?int $bookingId,
        ?int $createdBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): InventoryStockMovement {
        return InventoryStockMovement::query()->create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'branch_id' => $branchId,
            'booking_id' => $bookingId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
            'occurred_at' => now(),
        ]);
    }
}
