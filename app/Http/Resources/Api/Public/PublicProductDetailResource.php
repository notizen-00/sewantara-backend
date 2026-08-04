<?php

namespace App\Http\Resources\Api\Public;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use App\Modules\PublicApi\Read\Support\PublicMoney;
use Illuminate\Support\Collection;

final class PublicProductDetailResource
{
    /**
     * @param  Collection<int, Product>  $related
     * @return array<string, mixed>
     */
    public static function make(
        Product $product,
        Collection $related,
        string $currency,
    ): array {
        $category = $product->relationLoaded('category')
            ? $product->category
            : null;
        $money = app(PublicMoney::class);

        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => self::text($product->brand, 100),
            'model' => self::text($product->model, 100),
            'description' => self::text($product->description, 10000),
            'category' => $category instanceof Category ? [
                'id' => $category->public_id,
                'slug' => $category->slug,
                'name' => $category->name,
            ] : null,
            'media' => $product->images
                ->filter(fn (mixed $image): bool => $image instanceof ProductImage)
                ->map(fn (ProductImage $image): array => [
                    'id' => $image->public_id,
                    'url' => app(PublicMediaUrl::class)->productImage($image),
                    'alt' => self::text($image->alt_text, 255),
                    'is_primary' => (bool) $image->is_primary,
                    'sort_order' => (int) $image->sort_order,
                ])->values()->all(),
            'specifications' => self::safeValue($product->specifications),
            'variants' => [],
            'addons' => [],
            'pricing' => $product->prices
                ->filter(fn (mixed $price): bool => $price instanceof ProductPrice)
                ->map(fn (ProductPrice $price): array => [
                    'type' => $price->pricing_type,
                    'duration' => (int) $price->duration,
                    'amount' => $money->minorAmount($price->price, $currency),
                    'currency' => strtoupper($currency),
                ])->values()->all(),
            'booking_mode' => $product->getAttribute('public_booking_mode'),
            'inventory_type' => $product->getAttribute('public_inventory_type'),
            'minimum_rental_duration' => (int) $product->minimum_rental_duration,
            'booking_rules' => self::text($product->booking_rules, 5000),
            'cancellation_policy' => self::text(
                $product->cancellation_policy,
                5000,
            ),
            'deposit' => [
                'amount' => $money->minorAmount(
                    $product->deposit_amount,
                    $currency,
                ),
                'currency' => strtoupper($currency),
            ],
            'availability' => [
                'indicative' => true,
                'realtime' => (bool) $product->getAttribute(
                    'public_realtime_availability',
                ),
                'endpoint' => '/api/public/catalog/'
                    .rawurlencode($product->slug).'/availability',
            ],
            'related_products' => $related
                ->map(fn (Product $item): array => PublicProductCardResource::make(
                    $item,
                    $currency,
                ))->values()->all(),
            'seo' => [
                'title' => self::text($product->seo_title, 200) ?: $product->name,
                'description' => self::text(
                    $product->seo_description,
                    1000,
                ),
            ],
            'published_at' => $product->published_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    private static function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private static function safeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 4) {
            return null;
        }

        if (is_string($value)) {
            return self::text($value, 1000);
        }

        if (is_int($value) || is_bool($value) || is_float($value)
            || $value === null) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $safe = [];

        foreach (array_slice($value, 0, 100, true) as $key => $item) {
            $safeKey = is_int($key)
                ? $key
                : mb_substr(strip_tags((string) $key), 0, 100);
            $safe[$safeKey] = self::safeValue($item, $depth + 1);
        }

        return $safe;
    }
}
