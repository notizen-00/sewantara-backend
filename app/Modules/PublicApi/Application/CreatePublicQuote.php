<?php

namespace App\Modules\PublicApi\Application;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\PublicQuote;
use App\Models\TenantBusinessProfile;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Modules\PublicApi\Support\CanonicalPayload;
use App\Modules\PublicApi\Support\PublicMoney;
use App\Modules\RentalEngine\Application\RentalEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class CreatePublicQuote
{
    public function __construct(
        private readonly RentalEngine $rentalEngine,
        private readonly PublicAvailability $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, array $attributes): array
    {
        $this->guardUnsupportedPricingInputs($attributes);
        $product = $this->product((string) $attributes['product_id']);
        $branch = $this->branch();
        $prepared = $this->rentalEngine->prepareBooking($product->engine_code, [
            'product_id' => $product->getKey(),
            'branch_id' => $branch->getKey(),
            'start_at' => $attributes['booking']['starts_at'],
            'end_at' => $attributes['booking']['ends_at'],
            'booking_channel' => 'online',
        ]);
        $startsAt = CarbonImmutable::instance($prepared['start_at']);
        $endsAt = CarbonImmutable::instance($prepared['end_at']);
        $quantity = (int) $attributes['booking']['quantity'];

        if ($startsAt->lessThan(now()->subMinute())) {
            throw new PublicApiException(
                'VALIDATION_ERROR',
                'Waktu mulai pemesanan tidak boleh berada di masa lalu.',
                422,
                ['booking.starts_at' => ['Waktu mulai harus sekarang atau setelahnya.']],
            );
        }

        $this->availability->assertAvailable(
            $product,
            $branch,
            $startsAt,
            $endsAt,
            $quantity,
        );

        $currency = $this->currency();
        $price = $this->price($product, $branch, $startsAt);
        $unitAmount = PublicMoney::fromDatabase($price->price, $currency);
        $duration = $this->rentalEngine->billableDuration(
            $product->engine_code,
            $startsAt,
            $endsAt,
            (int) $price->duration,
        );
        $subtotal = $this->multiply($unitAmount, $duration, $quantity);
        $deposit = $this->multiply(
            PublicMoney::fromDatabase($product->deposit_amount, $currency),
            1,
            $quantity,
        );
        $expiresAt = now()->addMinutes(max(
            1,
            (int) config('public-api.quote_ttl_minutes', 15),
        ));
        $requestSnapshot = [
            'product_id' => $product->public_id,
            'variant_id' => null,
            'branch_id' => (int) $branch->getKey(),
            'booking' => [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'quantity' => $quantity,
            ],
            'addons' => [],
            'coupon_code' => null,
        ];
        $pricingSnapshot = [
            'currency' => $currency,
            'pricing_type' => (string) $price->pricing_type,
            'price_id' => (int) $price->getKey(),
            'price_duration' => (int) $price->duration,
            'duration' => $duration,
            'unit_amount' => $unitAmount,
            'subtotal' => $subtotal,
            'discount' => 0,
            'service_fee' => 0,
            'tax' => 0,
            'deposit' => $deposit,
            'grand_total' => $subtotal + $deposit,
            'payable_now' => $subtotal + $deposit,
        ];

        $quote = PublicQuote::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $product->getKey(),
            'branch_id' => $branch->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'quantity' => $quantity,
            'request_snapshot' => $requestSnapshot,
            'pricing_snapshot' => $pricingSnapshot,
            'request_hash' => CanonicalPayload::hash($requestSnapshot),
            'expires_at' => $expiresAt,
        ]);

        return $this->payload($quote, $product);
    }

    /** @return array<string, mixed> */
    private function payload(PublicQuote $quote, Product $product): array
    {
        $pricing = $quote->pricing_snapshot;

        return [
            'quote_id' => $quote->getKey(),
            'expires_at' => $quote->expires_at?->toIso8601String(),
            'currency' => $pricing['currency'],
            'lines' => [[
                'type' => 'rental',
                'label' => $product->name,
                'quantity' => $quote->quantity,
                'duration' => $pricing['duration'],
                'unit_amount' => $pricing['unit_amount'],
                'total_amount' => $pricing['subtotal'],
            ]],
            'subtotal' => $pricing['subtotal'],
            'discount' => $pricing['discount'],
            'service_fee' => $pricing['service_fee'],
            'tax' => $pricing['tax'],
            'deposit' => $pricing['deposit'],
            'grand_total' => $pricing['grand_total'],
            'payable_now' => $pricing['payable_now'],
        ];
    }

    private function product(string $publicId): Product
    {
        $product = Product::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if ($product === null) {
            throw new PublicApiException(
                'RESOURCE_NOT_FOUND',
                'Produk tidak ditemukan.',
                404,
            );
        }

        return $product;
    }

    private function branch(): Branch
    {
        $branch = Branch::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('is_primary', true)
            ->orderBy('id')
            ->first();

        if ($branch === null) {
            throw new PublicApiException(
                'PRODUCT_UNAVAILABLE',
                'Cabang publik untuk pemesanan belum tersedia.',
                409,
            );
        }

        return $branch;
    }

    private function price(
        Product $product,
        Branch $branch,
        CarbonImmutable $startsAt,
    ): ProductPrice {
        $pricingType = $this->rentalEngine->pricingType($product->engine_code);
        $price = ProductPrice::query()
            ->where('product_id', $product->getKey())
            ->where('pricing_type', $pricingType)
            ->where('is_active', true)
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->getKey())
                    ->orWhereNull('branch_id');
            })
            ->where(function ($query) use ($startsAt): void {
                $query->whereNull('start_at')->orWhere('start_at', '<=', $startsAt);
            })
            ->where(function ($query) use ($startsAt): void {
                $query->whereNull('end_at')->orWhere('end_at', '>', $startsAt);
            })
            ->orderByRaw(
                'CASE WHEN branch_id = ? THEN 0 ELSE 1 END',
                [$branch->getKey()],
            )
            ->orderByDesc('start_at')
            ->first();

        if ($price === null) {
            throw new PublicApiException(
                'PRODUCT_UNAVAILABLE',
                'Harga aktif untuk produk belum tersedia.',
                409,
            );
        }

        return $price;
    }

    private function currency(): string
    {
        return strtoupper((string) (
            TenantBusinessProfile::query()->value('currency')
            ?: tenant('currency')
            ?: config('public-api.defaults.currency', 'IDR')
        ));
    }

    /** @param array<string, mixed> $attributes */
    private function guardUnsupportedPricingInputs(array $attributes): void
    {
        if (filled($attributes['variant_id'] ?? null)) {
            throw new PublicApiException(
                'PRODUCT_UNAVAILABLE',
                'Varian produk belum tersedia untuk produk ini.',
                422,
            );
        }

        if (! empty($attributes['addons'] ?? [])) {
            throw new PublicApiException(
                'PRODUCT_UNAVAILABLE',
                'Add-on belum tersedia untuk produk ini.',
                422,
            );
        }

        if (filled($attributes['coupon_code'] ?? null)) {
            throw new PublicApiException(
                'COUPON_INVALID',
                'Kupon tidak valid.',
                422,
            );
        }
    }

    private function multiply(int $amount, int $duration, int $quantity): int
    {
        $total = $amount * $duration * $quantity;

        if ($amount < 0 || $duration < 1 || $quantity < 1 || $total < 0) {
            throw new PublicApiException(
                'INTERNAL_ERROR',
                'Perhitungan harga tidak dapat diproses.',
                500,
            );
        }

        return $total;
    }
}
