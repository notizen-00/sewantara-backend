<?php

namespace App\Http\Resources\Public\Home;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images->first();
        $price = $this->prices
            ->sortBy('amount')
            ->first();

        $amount = $price !== null
            ? (int) round((float) $price->amount)
            : 0;

        $currency = (string) (
            app('currentTenant')->currency
            ?? config(
                'public-api.defaults.currency',
                'IDR',
            )
        );

        return [
            'id' => (string) (
                $this->public_id
                ?: $this->getKey()
            ),

            'slug' => (string) $this->slug,

            'name' => (string) $this->name,

            'shortDescription' => (string) str(
                strip_tags(
                    (string) ($this->description ?? ''),
                ),
            )->limit(160),

            'category' => [
                'id' => (string) (
                    $this->category?->public_id
                    ?: $this->category?->getKey()
                    ?: ''
                ),

                'slug' => (string) (
                    $this->category?->slug
                    ?? ''
                ),

                'name' => (string) (
                    $this->category?->name
                    ?? ''
                ),
            ],

            'images' => $this->images
                ->map(
                    fn($image): array => [
                        'id' => (string) (
                            $image->public_id
                            ?: $image->getKey()
                        ),

                        'url' => (string) (
                            $image->image_url
                            ?? ''
                        ),

                        'alt' => (string) (
                            $image->alt_text
                            ?: $this->name
                        ),
                    ],
                )
                ->values()
                ->all(),

            'price' => [
                'base' => [
                    'amount' => $amount,
                    'currency' => $currency,
                    'formatted' => $this->formatMoney(
                        $amount,
                        $currency,
                    ),
                ],

                'unit' => $this->pricingUnit(
                    (string) $this->default_pricing_type,
                ),

                'unitLabel' => $this->pricingUnitLabel(
                    (string) $this->default_pricing_type,
                ),
            ],

            'bookingMode' => $this->bookingMode(
                (string) $this->default_pricing_type,
            ),

            'availability' => [
                'status' => 'available',
                'label' => 'Tersedia',
            ],

            'rating' => [
                'average' => 0,
                'count' => 0,
            ],

            'badges' => $this->is_featured
                ? ['Pilihan unggulan']
                : [],

            'featured' => (bool) $this->is_featured,

            'seo' => [
                'title' => (string) (
                    $this->seo_title
                    ?: $this->name
                ),

                'description' => (string) (
                    $this->seo_description
                    ?: str(
                        strip_tags(
                            (string) $this->description,
                        ),
                    )->limit(160)
                ),

                'ogImage' => (string) (
                    $primaryImage?->image_url
                    ?? ''
                ),
            ],
        ];
    }

    private function pricingUnit(string $type): string
    {
        return match ($type) {
            'hourly', 'hour' => 'hour',
            'daily', 'day' => 'day',
            'package' => 'package',
            default => 'item',
        };
    }

    private function pricingUnitLabel(string $type): string
    {
        return match ($type) {
            'hourly', 'hour' => 'per jam',
            'daily', 'day' => 'per hari',
            'package' => 'per paket',
            default => 'per item',
        };
    }

    private function bookingMode(string $type): string
    {
        return match ($type) {
            'hourly', 'hour' => 'hourly',
            'daily', 'day' => 'daily',
            'package' => 'package',
            default => 'date_range',
        };
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
