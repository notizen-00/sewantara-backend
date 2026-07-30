<?php

namespace App\Modules\Inventory\Application;

use App\Models\MaintenanceRecord;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageMaintenance
{
    public function __construct(
        private readonly RecordInventoryMovement $movements,
    ) {}

    public function paginate(
        ?string $status,
        ?int $productUnitId,
        int $perPage = 20,
        ?int $branchId = null,
    ): LengthAwarePaginator {
        return MaintenanceRecord::query()
            ->with('productUnit.product')
            ->when(
                $branchId,
                fn ($query, int $value) => $query->whereHas(
                    'productUnit',
                    fn ($unitQuery) => $unitQuery->where('branch_id', $value),
                ),
            )
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->when($productUnitId, fn ($query, int $value) => $query->where('product_unit_id', $value))
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(
        string $tenantId,
        ?string $actorId,
        array $attributes,
        ?int $branchId = null,
    ): MaintenanceRecord {
        $unit = ProductUnit::query()->findOrFail($attributes['product_unit_id']);

        if ($branchId !== null && (int) $unit->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'product_unit_id' => ['Unit produk tidak berada di cabang yang sedang dipilih.'],
            ]);
        }

        if (in_array($unit->status, ['reserved', 'rented', 'maintenance', 'lost', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'product_unit_id' => ["Unit {$unit->unit_code} tidak dapat dijadwalkan maintenance."],
            ]);
        }

        $hasOpenMaintenance = MaintenanceRecord::query()
            ->where('product_unit_id', $unit->getKey())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        if ($hasOpenMaintenance) {
            throw ValidationException::withMessages([
                'product_unit_id' => ["Unit {$unit->unit_code} masih memiliki maintenance aktif."],
            ]);
        }

        return MaintenanceRecord::query()->create([
            ...$attributes,
            'tenant_id' => $tenantId,
            'status' => 'scheduled',
            'created_by' => $actorId,
        ])->load('productUnit.product');
    }

    public function detail(MaintenanceRecord $maintenance): MaintenanceRecord
    {
        return $maintenance->load('productUnit.product');
    }

    public function start(
        MaintenanceRecord $maintenance,
        ?string $actorId,
    ): MaintenanceRecord {
        if ($maintenance->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['Hanya pemeliharaan terjadwal yang dapat dimulai.'],
            ]);
        }

        return DB::transaction(function () use ($maintenance, $actorId): MaintenanceRecord {
            $record = MaintenanceRecord::query()->lockForUpdate()
                ->findOrFail($maintenance->getKey());
            $unit = ProductUnit::query()->lockForUpdate()
                ->findOrFail($record->product_unit_id);

            if (in_array($unit->status, ['reserved', 'rented', 'lost', 'inactive'], true)) {
                throw ValidationException::withMessages([
                    'product_unit_id' => ["Unit {$unit->unit_code} sedang tidak tersedia untuk maintenance."],
                ]);
            }

            $fromStatus = $unit->status;
            $unit->update(['status' => 'maintenance']);
            $record->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            $this->movements->unit(
                $record->tenant_id,
                $unit,
                'maintenance_started',
                $fromStatus,
                'maintenance',
                null,
                $actorId,
                $record->title,
            );

            return $record->load('productUnit.product');
        });
    }

    public function complete(
        MaintenanceRecord $maintenance,
        ?string $actorId,
        array $attributes,
    ): MaintenanceRecord {
        if ($maintenance->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['Hanya pemeliharaan yang sedang berlangsung yang dapat diselesaikan.'],
            ]);
        }

        return DB::transaction(function () use ($maintenance, $actorId, $attributes): MaintenanceRecord {
            $record = MaintenanceRecord::query()->lockForUpdate()
                ->findOrFail($maintenance->getKey());
            $unit = ProductUnit::query()->lockForUpdate()
                ->findOrFail($record->product_unit_id);
            $nextStatus = $attributes['unit_status'] ?? 'available';

            $unit->update([
                'status' => $nextStatus,
                'condition' => $attributes['condition'] ?? $unit->condition,
                'current_meter' => $attributes['current_meter'] ?? $unit->current_meter,
            ]);
            $record->update([
                'status' => 'completed',
                'completed_at' => now(),
                'cost' => $attributes['cost'] ?? $record->cost,
                'description' => $attributes['description'] ?? $record->description,
            ]);
            $this->movements->unit(
                $record->tenant_id,
                $unit,
                'maintenance_completed',
                'maintenance',
                $nextStatus,
                null,
                $actorId,
                $record->title,
            );

            return $record->load('productUnit.product');
        });
    }

    public function cancel(
        MaintenanceRecord $maintenance,
        ?string $actorId,
        ?string $notes,
    ): MaintenanceRecord {
        if (! in_array($maintenance->status, ['scheduled', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pemeliharaan ini tidak dapat dibatalkan.'],
            ]);
        }

        return DB::transaction(function () use ($maintenance, $actorId, $notes): MaintenanceRecord {
            $record = MaintenanceRecord::query()->lockForUpdate()
                ->findOrFail($maintenance->getKey());
            $unit = ProductUnit::query()->lockForUpdate()
                ->findOrFail($record->product_unit_id);

            if ($record->status === 'in_progress') {
                $unit->update(['status' => 'available']);
                $this->movements->unit(
                    $record->tenant_id,
                    $unit,
                    'maintenance_cancelled',
                    'maintenance',
                    'available',
                    null,
                    $actorId,
                    $notes ?? $record->title,
                );
            }

            $record->update(['status' => 'cancelled']);

            return $record->load('productUnit.product');
        });
    }
}
