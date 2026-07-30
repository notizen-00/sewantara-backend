<?php

namespace App\Modules\Bookings\Application;

use App\Models\Booking;
use App\Modules\Inventory\Application\InventoryBookingLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageBookingStatus
{
    public function __construct(
        private readonly InventoryBookingLifecycle $inventory,
    ) {}

    public function checkOut(Booking $booking, ?int $actorId): Booking
    {
        $this->guardStatus(
            $booking,
            ['pending', 'confirmed', 'preparing', 'ready'],
            'Pesanan tidak dapat diserahkan kepada pelanggan dari status saat ini.',
        );

        return DB::transaction(function () use ($booking, $actorId): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->guardStatus(
                $locked,
                ['pending', 'confirmed', 'preparing', 'ready'],
                'Pesanan tidak dapat diserahkan kepada pelanggan dari status saat ini.',
            );
            $this->inventory->checkOut($locked, $actorId);
            $fromStatus = $locked->status;
            $locked->update([
                'status' => 'ongoing',
                'actual_start_at' => now(),
            ]);
            $this->recordStatus($locked, $fromStatus, 'ongoing', $actorId);

            return $locked->load(['items', 'allocations']);
        });
    }

    public function return(Booking $booking, ?int $actorId): Booking
    {
        $this->guardStatus(
            $booking,
            ['ongoing'],
            'Hanya pesanan yang sedang berlangsung yang dapat dikembalikan.',
        );

        return DB::transaction(function () use ($booking, $actorId): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->guardStatus(
                $locked,
                ['ongoing'],
                'Hanya pesanan yang sedang berlangsung yang dapat dikembalikan.',
            );
            $this->inventory->return($locked, $actorId);
            $locked->update([
                'status' => 'completed',
                'actual_end_at' => now(),
                'completed_at' => now(),
            ]);
            $this->recordStatus($locked, 'ongoing', 'completed', $actorId);

            return $locked->load(['items', 'allocations']);
        });
    }

    public function cancel(
        Booking $booking,
        ?int $actorId,
        ?string $notes,
    ): Booking {
        $this->guardStatus(
            $booking,
            ['draft', 'pending', 'confirmed', 'preparing', 'ready'],
            'Pesanan tidak dapat dibatalkan dari status saat ini.',
        );

        return DB::transaction(function () use ($booking, $actorId, $notes): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->guardStatus(
                $locked,
                ['draft', 'pending', 'confirmed', 'preparing', 'ready'],
                'Pesanan tidak dapat dibatalkan dari status saat ini.',
            );
            $fromStatus = $locked->status;
            $this->inventory->releaseReservation($locked, $actorId);
            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $this->recordStatus($locked, $fromStatus, 'cancelled', $actorId, $notes);

            return $locked->load(['items', 'allocations']);
        });
    }

    private function guardStatus(
        Booking $booking,
        array $allowed,
        string $message,
    ): void {
        if (! in_array($booking->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => [$message],
            ]);
        }
    }

    private function recordStatus(
        Booking $booking,
        string $fromStatus,
        string $toStatus,
        ?int $actorId,
        ?string $notes = null,
    ): void {
        DB::table('booking_status_histories')->insert([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'changed_by' => $actorId,
            'created_at' => now(),
        ]);
    }
}
