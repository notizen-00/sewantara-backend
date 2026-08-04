<?php

namespace App\Modules\PublicApi\Support;

use App\Models\Booking;

final class PublicStatus
{
    public static function booking(Booking $booking): string
    {
        return match ((string) $booking->status) {
            'pending' => self::hasRemainingAmount($booking->remaining_amount)
                ? 'awaiting_payment'
                : 'reserved',
            'preparing', 'ready' => 'confirmed',
            'ongoing' => 'in_progress',
            default => (string) $booking->status,
        };
    }

    public static function payment(?string $status): string
    {
        return match ($status) {
            null => 'unpaid',
            'partial' => 'pending',
            default => $status,
        };
    }

    private static function hasRemainingAmount(mixed $amount): bool
    {
        $normalized = ltrim(trim((string) $amount), '0');

        return $normalized !== '' && trim($normalized, '.0') !== '';
    }
}
