<?php

namespace App\Modules\PublicApi\Application;

use App\Models\Booking;
use App\Models\StockHold;
use App\Modules\Inventory\Application\InventoryBookingLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpirePublicStockHolds
{
    public function __construct(
        private readonly InventoryBookingLifecycle $inventory,
    ) {}

    public function execute(int $limit = 100): int
    {
        $ids = StockHold::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(max(1, min($limit, 1000)))
            ->pluck('id');
        $expired = 0;

        foreach ($ids as $id) {
            $expired += $this->expire((string) $id) ? 1 : 0;
        }

        return $expired;
    }

    private function expire(string $holdId): bool
    {
        return DB::transaction(function () use ($holdId): bool {
            $hold = StockHold::query()->lockForUpdate()->find($holdId);

            if ($hold === null
                || $hold->status !== 'active'
                || $hold->expires_at->isFuture()) {
                return false;
            }

            $booking = Booking::query()
                ->lockForUpdate()
                ->find($hold->booking_id);

            if ($booking === null) {
                $hold->forceFill(['status' => 'expired'])->save();

                return true;
            }

            if ($booking->payment_status === 'paid') {
                $hold->forceFill(['status' => 'converted'])->save();

                return false;
            }

            $fromStatus = (string) $booking->status;

            if (in_array($fromStatus, [
                'cancelled',
                'completed',
                'rejected',
                'refunded',
                'partially_refunded',
            ], true)) {
                $hold->forceFill(['status' => 'released'])->save();

                return false;
            }

            if (in_array($fromStatus, [
                'draft',
                'pending',
                'confirmed',
                'preparing',
                'ready',
            ], true)) {
                $this->inventory->releaseReservation($booking, null);
            }

            $hold->forceFill(['status' => 'expired'])->save();
            $booking->forceFill([
                'status' => 'expired',
                'expires_at' => $hold->expires_at,
            ])->save();
            DB::table('payments')
                ->where('booking_id', $booking->getKey())
                ->where('status', 'pending')
                ->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('booking_status_histories')
                && $fromStatus !== 'expired') {
                DB::table('booking_status_histories')->insert([
                    'tenant_id' => $booking->tenant_id,
                    'booking_id' => $booking->getKey(),
                    'from_status' => $fromStatus,
                    'to_status' => 'expired',
                    'notes' => 'Stock hold pembayaran kedaluwarsa.',
                    'changed_by' => null,
                    'created_at' => now(),
                ]);
            }

            return true;
        }, attempts: 3);
    }
}
