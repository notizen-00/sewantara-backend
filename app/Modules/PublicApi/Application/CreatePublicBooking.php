<?php

namespace App\Modules\PublicApi\Application;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\IdempotencyRecord;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PublicQuote;
use App\Models\StockHold;
use App\Modules\Bookings\Application\ManageBookings;
use App\Modules\PublicApi\Data\IdempotencyOutcome;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Modules\PublicApi\Support\CanonicalPayload;
use App\Modules\PublicApi\Support\PublicMoney;
use App\Modules\PublicApi\Support\PublicStatus;
use App\Modules\PublicApi\Support\TrackingToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePublicBooking
{
    public function __construct(
        private readonly ManageBookings $bookings,
        private readonly PublicAvailability $availability,
        private readonly PublicIdempotency $idempotency,
        private readonly PublicPayments $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        string $tenantId,
        string $idempotencyKey,
        array $attributes,
    ): IdempotencyOutcome {
        $endpoint = 'POST:/v1/public/bookings';
        $outcome = $this->idempotency->execute(
            $tenantId,
            $endpoint,
            $idempotencyKey,
            $attributes,
            fn (IdempotencyRecord $record): array => $this->create(
                $tenantId,
                $idempotencyKey,
                $attributes,
                $record,
            ),
            fn (IdempotencyRecord $record): array => $this->resume(
                $tenantId,
                $idempotencyKey,
                $attributes,
                $record,
            ),
        );
        $data = $outcome->data;
        $publicId = (string) ($data['booking_public_id'] ?? '');

        if ($publicId === '') {
            throw new PublicApiException(
                'INTERNAL_ERROR',
                'Respons pemesanan tidak dapat diproses.',
                500,
            );
        }

        unset($data['booking_public_id']);
        $data['tracking'] = [
            'token' => TrackingToken::derive(
                $tenantId,
                $publicId,
                $idempotencyKey,
            ),
        ];

        return new IdempotencyOutcome(
            $data,
            $outcome->status,
            $outcome->replayed,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{data: array<string, mixed>, status: int}
     */
    private function create(
        string $tenantId,
        string $idempotencyKey,
        array $attributes,
        IdempotencyRecord $record,
    ): array {
        $this->payments->assertMethodAvailable(
            (string) $attributes['payment_method'],
        );

        [$booking, $quote] = DB::transaction(function () use (
            $tenantId,
            $idempotencyKey,
            $attributes,
            $record,
        ): array {
            $quote = PublicQuote::query()
                ->with(['product', 'branch'])
                ->lockForUpdate()
                ->find($attributes['quote_id']);
            $this->guardQuote($quote);
            $product = $quote->product;
            $branch = $quote->branch;
            $this->availability->assertAvailable(
                $product,
                $branch,
                $quote->starts_at,
                $quote->ends_at,
                $quote->quantity,
                lock: true,
            );
            $unitIds = $this->availability->lockSerializedUnits(
                $product,
                $branch,
                $quote->starts_at,
                $quote->ends_at,
                $quote->quantity,
            );
            $customer = $this->customer($tenantId, $attributes['customer']);
            $booking = $this->bookings->create($tenantId, null, [
                'customer_id' => $customer->getKey(),
                'branch_id' => $branch->getKey(),
                'start_at' => $quote->starts_at,
                'end_at' => $quote->ends_at,
                'booking_channel' => 'online',
                'fulfillment_type' => 'pickup',
                'customer_notes' => $attributes['notes'] ?? null,
                'items' => [[
                    'product_id' => $product->getKey(),
                    'quantity' => $quote->quantity,
                    'unit_ids' => $unitIds,
                ]],
            ]);
            $this->guardRentalPricingSnapshot($booking, $quote);
            $this->applyPublicTotals($booking, $quote, $tenantId);
            $expiresAt = now()->addMinutes(max(
                1,
                (int) config('public-api.stock_hold_ttl_minutes', 20),
            ));
            $token = TrackingToken::derive(
                $tenantId,
                (string) $booking->public_id,
                $idempotencyKey,
            );
            $booking->forceFill([
                'tracking_token_hash' => TrackingToken::digest($token),
                'expires_at' => $expiresAt,
            ])->save();
            StockHold::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'quote_id' => $quote->getKey(),
                'booking_id' => $booking->getKey(),
                'product_id' => $product->getKey(),
                'branch_id' => $branch->getKey(),
                'starts_at' => $quote->starts_at,
                'ends_at' => $quote->ends_at,
                'quantity' => $quote->quantity,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);
            $quote->forceFill(['used_at' => now()])->save();
            $record->forceFill([
                'resource_type' => Booking::class,
                'resource_id' => (string) $booking->getKey(),
            ])->save();

            return [$booking->fresh(['items']), $quote];
        }, attempts: 3);

        $payment = $this->initializePayment(
            $tenantId,
            $booking,
            (string) $attributes['payment_method'],
        );

        return [
            'data' => $this->payload($booking, $quote, $payment),
            'status' => 201,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{data: array<string, mixed>, status: int}
     */
    private function resume(
        string $tenantId,
        string $idempotencyKey,
        array $attributes,
        IdempotencyRecord $record,
    ): array {
        $booking = Booking::query()
            ->with('items')
            ->find($record->resource_id);
        $hold = $booking === null
            ? null
            : StockHold::query()->where('booking_id', $booking->getKey())->first();
        $quote = $hold?->quote_id === null
            ? null
            : PublicQuote::query()->find($hold->quote_id);

        if ($booking === null || $quote === null) {
            throw new PublicApiException(
                'IDEMPOTENCY_IN_PROGRESS',
                'Permintaan sebelumnya belum dapat dipulihkan.',
                409,
            );
        }

        $expectedToken = TrackingToken::derive(
            $tenantId,
            (string) $booking->public_id,
            $idempotencyKey,
        );

        if (! TrackingToken::matches($booking->tracking_token_hash, $expectedToken)) {
            throw new PublicApiException(
                'INTERNAL_ERROR',
                'Akses tracking pemesanan tidak konsisten.',
                500,
            );
        }

        $payment = $this->initializePayment(
            $tenantId,
            $booking,
            (string) $attributes['payment_method'],
        );

        return [
            'data' => $this->payload($booking, $quote, $payment),
            'status' => 201,
        ];
    }

    /** @return array<string, mixed>|null */
    private function initializePayment(
        string $tenantId,
        Booking $booking,
        string $requestedMethod,
    ): ?array {
        try {
            return $this->payments->createForBooking(
                $tenantId,
                $booking,
                $requestedMethod,
            );
        } catch (PublicApiException $exception) {
            if ($exception->errorCode !== 'PAYMENT_INITIALIZATION_FAILED') {
                throw $exception;
            }

            $failed = Payment::query()
                ->where('booking_id', $booking->getKey())
                ->latest('id')
                ->first();

            return $failed === null ? [
                'method' => $requestedMethod,
                'status' => 'failed',
                'currency' => 'IDR',
            ] : $this->payments->paymentPayload($failed, $requestedMethod);
        }
    }

    private function guardQuote(?PublicQuote $quote): void
    {
        if ($quote === null) {
            throw new PublicApiException(
                'QUOTE_NOT_FOUND',
                'Quote tidak ditemukan.',
                404,
            );
        }

        if ($quote->used_at !== null) {
            throw new PublicApiException(
                'QUOTE_ALREADY_USED',
                'Quote sudah digunakan.',
                409,
            );
        }

        if ($quote->expires_at->isPast()) {
            throw new PublicApiException(
                'QUOTE_EXPIRED',
                'Quote sudah kedaluwarsa.',
                409,
            );
        }

        if (! hash_equals(
            $quote->request_hash,
            CanonicalPayload::hash($quote->request_snapshot),
        )) {
            throw new PublicApiException(
                'QUOTE_NOT_FOUND',
                'Quote tidak dapat diverifikasi.',
                404,
            );
        }

        if (! $quote->product instanceof Product
            || ! $quote->product->is_active
            || ! $quote->product->is_public
            || $quote->product->published_at === null
            || $quote->product->published_at->isFuture()
            || $quote->branch === null
            || ! $quote->branch->is_active
            || ! $quote->branch->is_public) {
            throw new PublicApiException(
                'PRODUCT_UNAVAILABLE',
                'Produk tidak lagi tersedia.',
                409,
            );
        }
    }

    private function guardRentalPricingSnapshot(
        Booking $booking,
        PublicQuote $quote,
    ): void {
        $item = $booking->items->sole();
        $pricing = $quote->pricing_snapshot;
        $currency = (string) ($pricing['currency'] ?? 'IDR');
        $matches = PublicMoney::fromDatabase($item->unit_price, $currency)
                === (int) ($pricing['unit_amount'] ?? -1)
            && (int) $item->duration === (int) ($pricing['duration'] ?? -1)
            && PublicMoney::fromDatabase($item->subtotal, $currency)
                === (int) ($pricing['subtotal'] ?? -1)
            && PublicMoney::fromDatabase($booking->deposit_amount, $currency)
                === (int) ($pricing['deposit'] ?? -1)
            && PublicMoney::fromDatabase($booking->total_amount, $currency)
                === (int) ($pricing['subtotal'] ?? -1);

        if (! $matches) {
            throw new PublicApiException(
                'AVAILABILITY_CHANGED',
                'Harga atau ketersediaan berubah. Buat quote baru.',
                409,
            );
        }
    }

    private function applyPublicTotals(
        Booking $booking,
        PublicQuote $quote,
        string $tenantId,
    ): void {
        $pricing = $quote->pricing_snapshot;
        $grandTotal = (int) ($pricing['grand_total'] ?? -1);
        $payableNow = (int) ($pricing['payable_now'] ?? -1);

        if ($grandTotal < 0 || $payableNow !== $grandTotal) {
            throw new PublicApiException(
                'INTERNAL_ERROR',
                'Snapshot total pemesanan tidak valid.',
                500,
            );
        }

        $booking->forceFill([
            'total_amount' => $grandTotal,
            'remaining_amount' => $payableNow,
        ])->save();
        DB::table('invoices')
            ->where('booking_id', $booking->getKey())
            ->update([
                'total_amount' => $grandTotal,
                'remaining_amount' => $payableNow,
                'updated_at' => now(),
            ]);

        $deposit = (int) ($pricing['deposit'] ?? 0);

        if ($deposit > 0) {
            DB::table('deposits')->insert([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->getKey(),
                'amount' => $deposit,
                'deducted_amount' => 0,
                'refunded_amount' => 0,
                'remaining_amount' => $deposit,
                'status' => 'pending',
                'held_at' => null,
                'refunded_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customer(string $tenantId, array $attributes): Customer
    {
        $customer = Customer::query()
            ->where('phone', $attributes['phone'])
            ->first();

        if ($customer?->status === 'blacklisted') {
            throw new PublicApiException(
                'BOOKING_CONFLICT',
                'Pemesanan tidak dapat diproses.',
                409,
            );
        }

        if ($customer !== null) {
            return $customer;
        }

        return Customer::query()->create([
            'tenant_id' => $tenantId,
            'name' => $attributes['name'],
            'phone' => $attributes['phone'],
            'email' => $attributes['email'] ?? null,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payment
     * @return array<string, mixed>
     */
    private function payload(
        Booking $booking,
        PublicQuote $quote,
        ?array $payment,
    ): array {
        $pricing = $quote->pricing_snapshot;

        return [
            // Used only to derive the token after idempotency persistence.
            'booking_public_id' => (string) $booking->public_id,
            'booking_code' => $booking->booking_number,
            'status' => PublicStatus::booking($booking),
            'payment_status' => PublicStatus::payment($booking->payment_status),
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'currency' => $pricing['currency'],
            'subtotal' => (int) $pricing['subtotal'],
            'deposit' => (int) $pricing['deposit'],
            'grand_total' => (int) $pricing['grand_total'],
            'payable_now' => (int) $pricing['payable_now'],
            'payment' => $payment,
        ];
    }
}
