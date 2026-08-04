<?php

namespace App\Modules\PublicApi\Application;

use App\Models\Booking;
use App\Models\IdempotencyRecord;
use App\Models\Payment;
use App\Models\TenantBusinessProfile;
use App\Models\TenantPaymentMethod;
use App\Modules\Payments\Application\CreateBookingPaymentCheckout;
use App\Modules\Payments\Application\RegisterPaymentWebhookRoute;
use App\Modules\PublicApi\Data\IdempotencyOutcome;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Modules\PublicApi\Support\PublicMoney;
use App\Modules\PublicApi\Support\PublicStatus;
use App\Modules\PublicApi\Support\TrackingToken;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicPayments
{
    public function __construct(
        private readonly CreateBookingPaymentCheckout $checkout,
        private readonly PublicIdempotency $idempotency,
        private readonly RegisterPaymentWebhookRoute $webhookRoutes,
    ) {}

    public function assertMethodAvailable(string $requestedMethod): string
    {
        $gateway = $this->gateway($requestedMethod);

        if (! TenantPaymentMethod::query()
            ->where('method', $gateway)
            ->where('is_enabled', true)
            ->exists()) {
            throw new PublicApiException(
                'PAYMENT_METHOD_UNAVAILABLE',
                'Metode pembayaran tidak tersedia.',
                422,
            );
        }

        if ($this->currency() !== 'IDR') {
            throw new PublicApiException(
                'PAYMENT_METHOD_UNAVAILABLE',
                'Payment gateway ini hanya mendukung IDR.',
                422,
            );
        }

        return $gateway;
    }

    /** @return array<string, mixed>|null */
    public function createForBooking(
        string $tenantId,
        Booking $booking,
        string $requestedMethod,
    ): ?array {
        $gateway = $this->assertMethodAvailable($requestedMethod);
        $amount = PublicMoney::fromDatabase($booking->remaining_amount, 'IDR');

        if ($amount < 1) {
            return null;
        }

        $existing = $this->latestPayment($booking);

        if ($existing !== null && $existing->status === 'pending') {
            return $this->paymentPayload($existing, $requestedMethod);
        }

        try {
            $result = $this->checkout->execute(
                tenantId: $tenantId,
                booking: $booking,
                type: 'full_payment',
                amount: $amount,
                gatewayName: $gateway,
                notificationUrl: $this->notificationUrl($gateway),
                createdBy: null,
            );
        } catch (ValidationException) {
            throw new PublicApiException(
                'PAYMENT_METHOD_UNAVAILABLE',
                'Metode pembayaran tidak tersedia untuk pesanan ini.',
                422,
            );
        } catch (Throwable) {
            throw new PublicApiException(
                'PAYMENT_INITIALIZATION_FAILED',
                'Pembayaran belum dapat diinisialisasi. Silakan coba kembali.',
                502,
            );
        }

        $expiresAt = now()->addMinutes(max(
            1,
            (int) config('public-api.payment_ttl_minutes', 30),
        ));

        if ($booking->expires_at !== null && $booking->expires_at->lessThan($expiresAt)) {
            $expiresAt = $booking->expires_at;
        }

        $result->payment->forceFill(['expired_at' => $expiresAt])->save();
        try {
            // The checkout action registered the route before its provider call.
            // Refreshing it here adds the final expiry without reopening checkout.
            $this->webhookRoutes->register(
                tenantId: $tenantId,
                payment: $result->payment,
                provider: (string) $result->payment->gateway,
                currency: 'IDR',
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return [
            'public_id' => $result->payment->public_id,
            'method' => $requestedMethod,
            'gateway' => $result->payment->gateway,
            'status' => PublicStatus::payment($result->payment->status),
            'amount' => $amount,
            'currency' => 'IDR',
            'redirect_url' => $result->checkout->redirectUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkoutForTrackedBooking(
        string $tenantId,
        string $bookingCode,
        string $trackingToken,
        string $requestedMethod,
        string $idempotencyKey,
        array $payload,
    ): IdempotencyOutcome {
        $booking = Booking::query()
            ->where('booking_number', $bookingCode)
            ->first();
        $this->guardBookingAccess($booking, $trackingToken);

        $endpoint = 'POST:/v1/public/bookings/{booking_code}/payments/checkout';

        return $this->idempotency->execute(
            $tenantId,
            $endpoint,
            $idempotencyKey,
            $payload,
            function (IdempotencyRecord $record) use (
                $tenantId,
                $booking,
                $requestedMethod,
            ): array {
                $this->guardPayableBooking($booking);
                $payment = $this->createForBooking(
                    $tenantId,
                    $booking,
                    $requestedMethod,
                );

                if ($payment === null) {
                    throw new PublicApiException(
                        'PAYMENT_ALREADY_PAID',
                        'Pesanan tidak memiliki tagihan tersisa.',
                        409,
                    );
                }

                $record->forceFill([
                    'resource_type' => Payment::class,
                    'resource_id' => $payment['public_id'],
                ])->save();

                return ['data' => $payment, 'status' => 201];
            },
            function (IdempotencyRecord $record) use ($requestedMethod): array {
                $payment = Payment::query()
                    ->where('public_id', $record->resource_id)
                    ->first();

                if ($payment === null) {
                    throw new PublicApiException(
                        'PAYMENT_NOT_FOUND',
                        'Pembayaran tidak ditemukan.',
                        404,
                    );
                }

                return [
                    'data' => $this->paymentPayload($payment, $requestedMethod),
                    'status' => 201,
                ];
            },
        );
    }

    /** @return array<string, mixed> */
    public function status(string $publicId, string $trackingToken): array
    {
        $payment = Payment::query()
            ->with('booking')
            ->where('public_id', $publicId)
            ->first();

        if ($payment === null
            || ! TrackingToken::matches(
                $payment->booking?->tracking_token_hash,
                $trackingToken,
            )) {
            throw new PublicApiException(
                'PAYMENT_NOT_FOUND',
                'Pembayaran tidak ditemukan.',
                404,
            );
        }

        return $this->paymentPayload($payment, $payment->gateway ?: $payment->method);
    }

    /** @return array<string, mixed> */
    public function paymentPayload(Payment $payment, string $requestedMethod): array
    {
        $transaction = $payment->transactions()
            ->whereNotNull('response_payload')
            ->latest('id')
            ->first();
        $redirectUrl = data_get($transaction?->response_payload, 'redirect_url');

        return array_filter([
            'public_id' => $payment->public_id,
            'method' => $requestedMethod,
            'gateway' => $payment->gateway,
            'status' => PublicStatus::payment($payment->status),
            'amount' => PublicMoney::fromDatabase($payment->amount, $this->currency()),
            'currency' => $this->currency(),
            'redirect_url' => is_string($redirectUrl) ? $redirectUrl : null,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'expires_at' => $payment->expired_at?->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function latestPayment(Booking $booking): ?Payment
    {
        return Payment::query()
            ->where('booking_id', $booking->getKey())
            ->whereIn('status', ['pending', 'failed'])
            ->latest('id')
            ->first();
    }

    private function guardBookingAccess(?Booking $booking, string $trackingToken): void
    {
        if ($booking === null
            || ! TrackingToken::matches($booking->tracking_token_hash, $trackingToken)) {
            throw new PublicApiException(
                'BOOKING_NOT_FOUND',
                'Pesanan tidak ditemukan.',
                404,
            );
        }
    }

    private function guardPayableBooking(Booking $booking): void
    {
        if (in_array($booking->status, ['cancelled', 'completed', 'expired'], true)) {
            throw new PublicApiException(
                'PAYMENT_EXPIRED',
                'Pesanan tidak lagi dapat dibayar.',
                409,
            );
        }

        if ($booking->payment_status === 'paid') {
            throw new PublicApiException(
                'PAYMENT_ALREADY_PAID',
                'Pesanan sudah dibayar.',
                409,
            );
        }
    }

    private function gateway(string $requestedMethod): string
    {
        return $requestedMethod === 'qris'
            ? (string) config('payments.default', 'midtrans')
            : $requestedMethod;
    }

    private function notificationUrl(string $gateway): string
    {
        return rtrim((string) config('app.url'), '/')
            .'/v1/webhooks/payments/'.rawurlencode($gateway);
    }

    private function currency(): string
    {
        return strtoupper((string) (
            TenantBusinessProfile::query()->value('currency')
            ?: tenant('currency')
            ?: config('public-api.defaults.currency', 'IDR')
        ));
    }
}
