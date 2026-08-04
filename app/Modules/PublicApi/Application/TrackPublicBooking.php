<?php

namespace App\Modules\PublicApi\Application;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TenantBusinessProfile;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Modules\PublicApi\Support\CustomerContact;
use App\Modules\PublicApi\Support\PublicMoney;
use App\Modules\PublicApi\Support\PublicStatus;
use App\Modules\PublicApi\Support\TrackingToken;

class TrackPublicBooking
{
    /**
     * @param  array{type: string, value: string}  $verifier
     * @return array<string, mixed>
     */
    public function execute(
        string $bookingCode,
        array $verifier,
        string $trackingToken,
    ): array {
        $booking = Booking::query()
            ->with(['customer', 'items'])
            ->where('booking_number', $bookingCode)
            ->first();

        if ($booking === null
            || ! TrackingToken::matches(
                $booking->tracking_token_hash,
                $trackingToken,
            )
            || ! $this->verifierMatches($booking, $verifier)) {
            throw new PublicApiException(
                'TRACKING_VERIFICATION_FAILED',
                'Data tracking tidak dapat diverifikasi.',
                404,
            );
        }

        $payment = Payment::query()
            ->where('booking_id', $booking->getKey())
            ->latest('id')
            ->first();
        $currency = strtoupper((string) (
            TenantBusinessProfile::query()->value('currency')
            ?: tenant('currency')
            ?: config('public-api.defaults.currency', 'IDR')
        ));

        return [
            'booking_code' => $booking->booking_number,
            'status' => PublicStatus::booking($booking),
            'payment_status' => PublicStatus::payment($booking->payment_status),
            'starts_at' => $booking->start_at?->toIso8601String(),
            'ends_at' => $booking->end_at?->toIso8601String(),
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'customer' => array_filter([
                'name' => $this->maskName((string) $booking->customer->name),
                'phone' => CustomerContact::maskPhone((string) $booking->customer->phone),
                'email' => CustomerContact::maskEmail($booking->customer->email),
            ], static fn (mixed $value): bool => $value !== null),
            'items' => $booking->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'duration' => (int) $item->duration,
                'unit_amount' => PublicMoney::fromDatabase($item->unit_price, $currency),
                'total_amount' => PublicMoney::fromDatabase($item->total_amount, $currency),
            ])->values()->all(),
            'amounts' => [
                'currency' => $currency,
                'subtotal' => PublicMoney::fromDatabase($booking->subtotal, $currency),
                'deposit' => PublicMoney::fromDatabase($booking->deposit_amount, $currency),
                'total' => PublicMoney::fromDatabase($booking->total_amount, $currency),
                'paid' => PublicMoney::fromDatabase($booking->paid_amount, $currency),
                'remaining' => PublicMoney::fromDatabase($booking->remaining_amount, $currency),
            ],
            'payment' => $payment === null ? null : [
                'public_id' => $payment->public_id,
                'method' => $payment->method,
                'gateway' => $payment->gateway,
                'status' => PublicStatus::payment($payment->status),
                'expires_at' => $payment->expired_at?->toIso8601String(),
            ],
        ];
    }

    /** @param array{type: string, value: string} $verifier */
    private function verifierMatches(Booking $booking, array $verifier): bool
    {
        $expected = match ($verifier['type']) {
            'phone' => CustomerContact::phone((string) $booking->customer?->phone),
            'email' => CustomerContact::email((string) $booking->customer?->email),
            default => '',
        };

        return $expected !== '' && hash_equals($expected, $verifier['value']);
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return implode(' ', array_map(static function (string $part): string {
            $first = mb_substr($part, 0, 1);

            return $first.str_repeat('*', max(2, mb_strlen($part) - 1));
        }, $parts));
    }
}
