<?php

namespace App\Http\Resources\PublicApi;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductDetailResource extends ProductCardResource
{
    public function __construct(
        mixed $resource,
        private readonly Collection $relatedProducts,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $currency = strtoupper((string) app('currentTenant')->currency);
        $card = parent::toArray($request);

        return [
            ...$card,
            'description' => $this->plainText($product->description, 20000),
            'media' => $product->relationLoaded('images')
                ? $product->images
                    ->map(fn (ProductImage $image): array => [
                        'url' => app(PublicMediaUrl::class)->productImage($image),
                        'alt' => $this->plainText(
                            $image->alt_text ?: $product->name,
                            200,
                        ),
                        'primary' => (bool) $image->is_primary,
                    ])
                    ->filter(fn (array $image): bool => $image['url'] !== null)
                    ->values()
                    ->all()
                : [],
            'specifications' => $this->specifications($product->specifications),
            'variants' => [],
            'add_ons' => [],
            'pricing' => [
                ...$card['pricing'],
                'options' => $product->relationLoaded('prices')
                    ? $product->prices->map(fn (ProductPrice $price): array => [
                        'pricing_type' => $price->pricing_type,
                        'duration' => (int) $price->duration,
                        'amount' => $this->money($price->price, $currency),
                        'starts_at' => $price->start_at?->toIso8601String(),
                        'ends_at' => $price->end_at?->toIso8601String(),
                    ])->values()->all()
                    : [],
            ],
            'booking_rules' => $this->plainText($product->booking_rules, 10000),
            'availability_summary' => [
                'realtime' => true,
                'indicative' => true,
                'endpoint' => '/v1/public/catalog/'.rawurlencode($product->slug)
                    .'/availability',
            ],
            'deposit_policy' => [
                'required' => (float) $product->deposit_amount > 0,
                'amount' => $this->money($product->deposit_amount, $currency),
            ],
            'late_fee' => $this->money($product->late_fee_amount, $currency),
            'cancellation_policy' => $this->plainText(
                $product->cancellation_policy,
                10000,
            ),
            'related_products' => $this->relatedProducts
                ->map(fn (Product $related): array => (new ProductCardResource($related))
                    ->toArray($request))
                ->values()
                ->all(),
            'seo' => [
                'title' => $this->plainText(
                    $product->seo_title ?: $product->name,
                    200,
                ),
                'description' => $this->plainText(
                    $product->seo_description ?: $product->description,
                    500,
                ),
            ],
        ];
    }

    /** @return list<array{label: string, value: string, unit: string|null}> */
    private function specifications(mixed $specifications): array
    {
        if (! is_array($specifications)) {
            return [];
        }

        $result = [];

        if (! array_is_list($specifications)) {
            foreach (array_slice($specifications, 0, 50, true) as $label => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $safeLabel = $this->plainText((string) $label, 120);
                $safeValue = $this->plainText((string) $value, 500);

                if ($safeLabel !== null && $safeValue !== null) {
                    $result[] = [
                        'label' => $safeLabel,
                        'value' => $safeValue,
                        'unit' => null,
                    ];
                }
            }

            return $result;
        }

        foreach (array_slice($specifications, 0, 50) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $this->plainText(
                $item['label'] ?? $item['name'] ?? null,
                120,
            );
            $value = $this->plainText($item['value'] ?? null, 500);

            if ($label !== null && $value !== null) {
                $result[] = [
                    'label' => $label,
                    'value' => $value,
                    'unit' => $this->plainText($item['unit'] ?? null, 50),
                ];
            }
        }

        return $result;
    }
}
