<?php

namespace App\Http\Resources\Api\Public;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use App\Modules\PublicApi\Read\Support\PublicMoney;

final class PublicProductCardResource
{
    /** @return array<string, mixed> */
    public static function make(Product $product, string $currency): array
    {
        $category = $product->relationLoaded('category')
            ? $product->category
            : null;
        $image = $product->relationLoaded('images')
            ? $product->images->first()
            : null;
        $startingPrice = $product->getAttribute('starting_price');

        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => self::text($product->brand, 100),
            'model' => self::text($product->model, 100),
            'summary' => self::text($product->description, 300),
            'category' => $category instanceof Category ? [
                'id' => $category->public_id,
                'slug' => $category->slug,
                'name' => $category->name,
            ] : null,
            'image_url' => $image instanceof ProductImage
                ? app(PublicMediaUrl::class)->productImage($image)
                : null,
            'starting_price' => $startingPrice === null ? null : [
                'amount' => app(PublicMoney::class)->minorAmount(
                    $startingPrice,
                    $currency,
                ),
                'currency' => strtoupper($currency),
                'pricing_type' => $product->default_pricing_type,
            ],
            'booking_mode' => $product->getAttribute('public_booking_mode'),
            'inventory_type' => $product->getAttribute('public_inventory_type'),
            'is_featured' => (bool) $product->is_featured,
            'published_at' => $product->published_at?->toIso8601String(),
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
}
