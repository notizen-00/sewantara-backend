<?php

namespace App\Modules\Inventory\Application;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingUnitAllocation;
use App\Models\InventoryStock;
use App\Models\ProductUnit;
use Illuminate\Validation\ValidationException;

class InventoryBookingLifecycle
{
    public function __construct(
        private readonly RecordInventoryMovement $movements,
    ) {}

    public function reserve(Booking $booking, ?string $actorId): void
    {
        foreach ($booking->items()->get() as $item) {
            if ($item->inventory_type === 'serialized') {
                $this->reserveSerializedItem($booking, $item, $actorId);

                continue;
            }

            $stock = $this->lockStock($booking, $item);
            $availableBefore = $this->available($stock);

            if ($availableBefore < $item->quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Stok {$item->product_name} tidak mencukupi pada branch booking."],
                ]);
            }

            $stock->increment('quantity_reserved', $item->quantity);
            $this->movements->stock(
                $booking->tenant_id,
                (int) $item->product_id,
                (int) $booking->branch_id,
                'booking_reserved',
                -$item->quantity,
                $availableBefore,
                $availableBefore - $item->quantity,
                $booking->getKey(),
                $actorId,
                Booking::class,
                $booking->getKey(),
                "Reservasi {$booking->booking_number}",
            );
        }
    }

    public function checkOut(Booking $booking, ?string $actorId): void
    {
        foreach ($booking->items()->get() as $item) {
            if ($item->inventory_type === 'serialized') {
                foreach ($this->allocations($booking, $item) as $allocation) {
                    $unit = ProductUnit::query()->lockForUpdate()
                        ->findOrFail($allocation->product_unit_id);
                    $fromStatus = $unit->status;

                    $unit->update(['status' => 'rented']);
                    $allocation->update([
                        'status' => 'checked_out',
                        'checked_out_at' => now(),
                    ]);
                    $this->movements->unit(
                        $booking->tenant_id,
                        $unit,
                        'booking_checked_out',
                        $fromStatus,
                        'rented',
                        $booking->getKey(),
                        $actorId,
                        "Check-out {$booking->booking_number}",
                    );
                }

                continue;
            }

            $stock = $this->lockStock($booking, $item);

            if ($stock->quantity_reserved < $item->quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Reservasi stok {$item->product_name} tidak konsisten."],
                ]);
            }

            $stock->decrement('quantity_reserved', $item->quantity);
            $stock->increment('quantity_rented', $item->quantity);
            $this->movements->stock(
                $booking->tenant_id,
                (int) $item->product_id,
                (int) $booking->branch_id,
                'booking_checked_out',
                $item->quantity,
                $this->available($stock),
                $this->available($stock),
                $booking->getKey(),
                $actorId,
                Booking::class,
                $booking->getKey(),
                "Check-out {$booking->booking_number}",
            );
        }
    }

    public function return(Booking $booking, ?string $actorId): void
    {
        foreach ($booking->items()->get() as $item) {
            if ($item->inventory_type === 'serialized') {
                foreach ($this->allocations($booking, $item) as $allocation) {
                    $unit = ProductUnit::query()->lockForUpdate()
                        ->findOrFail($allocation->product_unit_id);

                    $allocation->update([
                        'status' => 'returned',
                        'returned_at' => now(),
                    ]);
                    $nextStatus = $this->activeStatus($unit);
                    $fromStatus = $unit->status;
                    $unit->update(['status' => $nextStatus]);
                    $this->movements->unit(
                        $booking->tenant_id,
                        $unit,
                        'booking_returned',
                        $fromStatus,
                        $nextStatus,
                        $booking->getKey(),
                        $actorId,
                        "Pengembalian {$booking->booking_number}",
                    );
                }

                continue;
            }

            $stock = $this->lockStock($booking, $item);

            if ($stock->quantity_rented < $item->quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Stok tersewa {$item->product_name} tidak konsisten."],
                ]);
            }

            $availableBefore = $this->available($stock);
            $stock->decrement('quantity_rented', $item->quantity);
            $this->movements->stock(
                $booking->tenant_id,
                (int) $item->product_id,
                (int) $booking->branch_id,
                'booking_returned',
                $item->quantity,
                $availableBefore,
                $availableBefore + $item->quantity,
                $booking->getKey(),
                $actorId,
                Booking::class,
                $booking->getKey(),
                "Pengembalian {$booking->booking_number}",
            );
        }
    }

    public function releaseReservation(Booking $booking, ?string $actorId): void
    {
        foreach ($booking->items()->get() as $item) {
            if ($item->inventory_type === 'serialized') {
                foreach ($this->allocations($booking, $item) as $allocation) {
                    if ($allocation->status !== 'reserved') {
                        continue;
                    }

                    $unit = ProductUnit::query()->lockForUpdate()
                        ->findOrFail($allocation->product_unit_id);
                    $allocation->update(['status' => 'cancelled']);
                    $nextStatus = $this->activeStatus($unit);
                    $fromStatus = $unit->status;
                    $unit->update(['status' => $nextStatus]);
                    $this->movements->unit(
                        $booking->tenant_id,
                        $unit,
                        'booking_cancelled',
                        $fromStatus,
                        $nextStatus,
                        $booking->getKey(),
                        $actorId,
                        "Pembatalan {$booking->booking_number}",
                    );
                }

                continue;
            }

            $stock = $this->lockStock($booking, $item);

            if ($stock->quantity_reserved < $item->quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Reservasi stok {$item->product_name} tidak konsisten."],
                ]);
            }

            $availableBefore = $this->available($stock);
            $stock->decrement('quantity_reserved', $item->quantity);
            $this->movements->stock(
                $booking->tenant_id,
                (int) $item->product_id,
                (int) $booking->branch_id,
                'booking_cancelled',
                $item->quantity,
                $availableBefore,
                $availableBefore + $item->quantity,
                $booking->getKey(),
                $actorId,
                Booking::class,
                $booking->getKey(),
                "Pembatalan {$booking->booking_number}",
            );
        }
    }

    private function reserveSerializedItem(
        Booking $booking,
        BookingItem $item,
        ?string $actorId,
    ): void {
        foreach ($this->allocations($booking, $item) as $allocation) {
            $unit = ProductUnit::query()->lockForUpdate()
                ->findOrFail($allocation->product_unit_id);
            $fromStatus = $unit->status;

            if (! in_array($fromStatus, ['available', 'reserved'], true)) {
                throw ValidationException::withMessages([
                    'items' => ["Unit {$unit->unit_code} tidak tersedia."],
                ]);
            }

            $unit->update(['status' => 'reserved']);
            $this->movements->unit(
                $booking->tenant_id,
                $unit,
                'booking_reserved',
                $fromStatus,
                'reserved',
                $booking->getKey(),
                $actorId,
                "Reservasi {$booking->booking_number}",
            );
        }
    }

    private function lockStock(Booking $booking, BookingItem $item): InventoryStock
    {
        if ($booking->branch_id === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['Cabang wajib dipilih untuk produk yang dikelola berdasarkan jumlah stok.'],
            ]);
        }

        return InventoryStock::query()
            ->where('product_id', $item->product_id)
            ->where('branch_id', $booking->branch_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function available(InventoryStock $stock): int
    {
        return $stock->quantity_total
            - $stock->quantity_reserved
            - $stock->quantity_rented
            - $stock->quantity_maintenance
            - $stock->quantity_damaged
            - $stock->quantity_lost;
    }

    private function allocations(
        Booking $booking,
        BookingItem $item,
    ): iterable {
        return BookingUnitAllocation::query()
            ->where('booking_id', $booking->getKey())
            ->where('booking_item_id', $item->getKey())
            ->get();
    }

    private function activeStatus(ProductUnit $unit): string
    {
        $statuses = BookingUnitAllocation::query()
            ->where('product_unit_id', $unit->getKey())
            ->whereIn('status', ['reserved', 'checked_out'])
            ->pluck('status');

        if ($statuses->contains('checked_out')) {
            return 'rented';
        }

        return $statuses->contains('reserved') ? 'reserved' : 'available';
    }
}
