<?php

namespace App\Http\Resources\PublicApi;

use App\Models\Product;
use App\Models\ProductImage;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use App\Modules\PublicApi\Read\Support\PublicMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $category = $product->relationLoaded('category') ? $product->category : null;
        $primaryImage = $product->relationLoaded('images')
            ? $product->images->first()
            : null;
        $currency = strtoupper((string) app('currentTenant')->currency);

        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            'name' => $product->name,
            'summary' => $this->plainText($product->description, 280),
            'brand' => $this->plainText($product->brand, 100),
            'model' => $this->plainText($product->model, 100),
            'category' => $category === null ? null : [
                'id' => $category->public_id,
                'slug' => $category->slug,
                'name' => $category->name,
            ],
            'primary_image' => $primaryImage instanceof ProductImage ? [
                'url' => app(PublicMediaUrl::class)->productImage($primaryImage),
                'alt' => $this->plainText(
                    $primaryImage->alt_text ?: $product->name,
                    200,
                ),
            ] : null,
            'pricing' => [
                'starts_from' => $this->money($product->starting_price, $currency),
                'pricing_type' => $product->default_pricing_type,
                'minimum_duration' => (int) $product->minimum_rental_duration,
            ],
            'booking_mode' => $product->public_booking_mode,
            'inventory_type' => $product->public_inventory_type,
            'featured' => (bool) $product->is_featured,
        ];
    }

    /** @return array{amount: int, currency: string}|null */
    protected function money(mixed $amount, string $currency): ?array
    {
        if ($amount === null) {
            return null;
        }

        try {
            return app(PublicMoney::class)->payload($amount, $currency);
        } catch (Throwable) {
            return null;
        }
    }

    protected function plainText(mixed $value, int $maximumLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, $maximumLength);
    }
}
