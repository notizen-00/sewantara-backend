<?php

namespace App\Http\Resources\Public\Catalog;

use App\Modules\PublicApi\DTO\Catalog\PublicProductData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicProductData $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,

            'shortDescription' => $product->shortDescription,

            'description' => $product->description,

            'category' => $product->category,

            'images' => $product->images,

            'price' => [
                'base' => [
                    'amount' => $product->priceAmount,
                    'currency' => $product->currency,
                    'formatted' => $this->formatMoney(
                        $product->priceAmount,
                        $product->currency,
                    ),
                ],

                'unit' => $product->pricingUnit,

                'unitLabel' => $product->pricingUnitLabel,
            ],

            'bookingMode' => $product->bookingMode,

            'bookingRules' => $product->bookingRules,

            'extraServices' => [],

            'locations' => [],

            'availability' => $product->availability,

            'rating' => $product->rating,

            'badges' => $product->badges,

            'specifications' => $product->specifications,

            'featured' => $product->featured,

            'seo' => $product->seo,
        ];
    }

    private function formatMoney(
        int $amount,
        string $currency,
    ): string {
        if ($currency === 'IDR') {
            return 'Rp ' . number_format(
                $amount,
                0,
                ',',
                '.',
            );
        }

        return $currency . ' ' . number_format(
            $amount,
            2,
            '.',
            ',',
        );
    }
}
