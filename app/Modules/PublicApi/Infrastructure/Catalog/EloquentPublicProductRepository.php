<?php

namespace App\Modules\PublicApi\Infrastructure\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Modules\PublicApi\Domain\Catalog\Contracts\PublicProductRepositoryContract;
use App\Modules\PublicApi\DTO\Catalog\PublicCatalogQuery;
use App\Modules\PublicApi\DTO\Catalog\PublicProductData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentPublicProductRepository implements PublicProductRepositoryContract
{
    public function paginate(
        PublicCatalogQuery $query,
    ): LengthAwarePaginator {
        $builder = Product::query()
            ->publiclyVisible()
            ->select([
                'id',
                'public_id',
                'category_id',
                'name',
                'slug',
                'description',
                'brand',
                'model',
                'inventory_type',
                'default_pricing_type',
                'minimum_rental_duration',
                'deposit_amount',
                'is_featured',
                'published_at',
                'specifications',
                'seo_title',
                'seo_description',
                'booking_rules',
            ])
            ->with([
                'category:id,public_id,name,slug',

                'images:id,public_id,product_id,image_path,alt_text,is_primary,sort_order',

                'prices' => function ($prices): void {
                    $prices
                        ->where('is_active', true)
                        ->where(function ($period): void {
                            $period
                                ->whereNull('start_at')
                                ->orWhere('start_at', '<=', now());
                        })
                        ->where(function ($period): void {
                            $period
                                ->whereNull('end_at')
                                ->orWhere('end_at', '>=', now());
                        })
                        ->orderBy('price');
                },
            ]);

        $this->applySearch($builder, $query);
        $this->applyCategory($builder, $query);
        $this->applyPrice($builder, $query);
        $this->applyBookingMode($builder, $query);
        $this->applyFeatured($builder, $query);
        $this->applySort($builder, $query);

        $paginator = $builder->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(
                    fn(Product $product): PublicProductData => $this->toData(
                        $product,
                    ),
                ),
        );

        return $paginator;
    }

    public function categories(): Collection
    {
        return Category::query()
            ->publiclyVisible()
            ->whereHas(
                'products',
                fn(Builder $products): Builder => $products
                    ->publiclyVisible(),
            )
            ->withCount([
                'products as product_count' => fn(Builder $products): Builder => $products
                    ->publiclyVisible(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'public_id',
                'name',
                'slug',
            ]);
    }

    private function applySearch(
        Builder $builder,
        PublicCatalogQuery $query,
    ): void {
        if ($query->search === null || $query->search === '') {
            return;
        }

        $term = '%' . $query->search . '%';

        $builder->where(function (Builder $search) use ($term): void {
            $search
                ->where('name', 'ilike', $term)
                ->orWhere('description', 'ilike', $term)
                ->orWhere('brand', 'ilike', $term)
                ->orWhere('model', 'ilike', $term)
                ->orWhereHas(
                    'category',
                    fn(Builder $category): Builder => $category
                        ->where('name', 'ilike', $term),
                );
        });
    }

    private function applyCategory(
        Builder $builder,
        PublicCatalogQuery $query,
    ): void {
        if ($query->category === null) {
            return;
        }

        $builder->whereHas(
            'category',
            fn(Builder $category): Builder => $category
                ->publiclyVisible()
                ->where('slug', $query->category),
        );
    }

    private function applyPrice(
        Builder $builder,
        PublicCatalogQuery $query,
    ): void {
        if ($query->minimumPrice === null && $query->maximumPrice === null) {
            return;
        }

        $builder->whereHas(
            'prices',
            function (Builder $prices) use ($query): void {
                $prices->where('is_active', true);

                if ($query->minimumPrice !== null) {
                    $prices->where(
                        'price',
                        '>=',
                        $query->minimumPrice,
                    );
                }

                if ($query->maximumPrice !== null) {
                    $prices->where(
                        'price',
                        '<=',
                        $query->maximumPrice,
                    );
                }
            },
        );
    }

    private function applyBookingMode(
        Builder $builder,
        PublicCatalogQuery $query,
    ): void {
        if ($query->bookingMode === null) {
            return;
        }

        $pricingTypes = match ($query->bookingMode) {
            'hourly' => ['hour', 'hourly'],
            'daily' => ['day', 'daily'],
            'package' => ['package'],
            'date_range' => ['date_range', 'daily', 'day'],
            'time_slot' => ['time_slot', 'hourly', 'hour'],
            default => [],
        };

        if ($pricingTypes !== []) {
            $builder->whereIn(
                'default_pricing_type',
                $pricingTypes,
            );
        }
    }

    private function applyFeatured(
        Builder $builder,
        PublicCatalogQuery $query,
    ): void {
        if ($query->featured !== null) {
            $builder->where(
                'is_featured',
                $query->featured,
            );
        }
    }

    private function applySort(
        Builder $builder,
        PublicCatalogQuery $query,
    ): void {
        match ($query->sort) {
            'newest' => $builder
                ->orderByDesc('published_at')
                ->orderByDesc('id'),

            'name_asc' => $builder
                ->orderBy('name')
                ->orderBy('id'),

            'price_asc' => $this->sortByPrice(
                $builder,
                'asc',
            ),

            'price_desc' => $this->sortByPrice(
                $builder,
                'desc',
            ),

            'popular' => $builder
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at'),

            default => $builder
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id'),
        };
    }

    private function sortByPrice(
        Builder $builder,
        string $direction,
    ): void {
        $builder->orderBy(
            ProductPrice::query()
                ->select('price')
                ->whereColumn(
                    'product_prices.product_id',
                    'products.id',
                )
                ->where('is_active', true)
                ->orderBy('price')
                ->limit(1),
            $direction,
        );
    }

    private function toData(Product $product): PublicProductData
    {
        $price = $product->prices->first();

        $priceAmount = $price !== null
            ? (int) round((float) $price->price)
            : 0;

        $currency = (string) (
            app('currentTenant')->currency
            ?? config('public-api.defaults.currency', 'IDR')
        );

        $primaryImage = $product->images->first();

        return new PublicProductData(
            id: (string) (
                $product->public_id
                ?: $product->getKey()
            ),

            slug: (string) $product->slug,

            name: (string) $product->name,

            shortDescription: (string) str(
                strip_tags((string) $product->description),
            )->limit(160),

            description: (string) ($product->description ?? ''),

            category: [
                'id' => (string) (
                    $product->category?->public_id
                    ?: $product->category?->getKey()
                    ?: ''
                ),
                'slug' => (string) ($product->category?->slug ?? ''),
                'name' => (string) ($product->category?->name ?? ''),
            ],

            images: $product->images
                ->map(fn($image): array => [
                    'id' => (string) (
                        $image->public_id
                        ?: $image->getKey()
                    ),
                    'url' => (string) ($image->image_url ?? ''),
                    'alt' => (string) (
                        $image->alt_text
                        ?: $product->name
                    ),
                ])
                ->values()
                ->all(),

            priceAmount: $priceAmount,

            currency: $currency,

            pricingUnit: $this->pricingUnit(
                (string) $product->default_pricing_type,
            ),

            pricingUnitLabel: $this->pricingUnitLabel(
                (string) $product->default_pricing_type,
            ),

            bookingMode: $this->bookingMode(
                (string) $product->default_pricing_type,
            ),

            bookingRules: is_array($product->booking_rules)
                ? $product->booking_rules
                : [],

            availability: [
                'status' => 'available',
                'label' => 'Tersedia',
            ],

            rating: [
                'average' => 0,
                'count' => 0,
            ],

            badges: $product->is_featured
                ? ['Pilihan unggulan']
                : [],

            specifications: $this->specifications(
                $product->specifications,
            ),

            featured: (bool) $product->is_featured,

            seo: [
                'title' => (string) (
                    $product->seo_title
                    ?: $product->name
                ),
                'description' => (string) (
                    $product->seo_description
                    ?: str(
                        strip_tags((string) $product->description),
                    )->limit(160)
                ),
                'ogImage' => (string) (
                    $primaryImage?->image_url
                    ?? ''
                ),
            ],
        );
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function specifications(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return collect($value)
                ->filter(fn(mixed $item): bool => is_array($item))
                ->map(fn(array $item): array => [
                    'label' => (string) ($item['label'] ?? ''),
                    'value' => (string) ($item['value'] ?? ''),
                ])
                ->values()
                ->all();
        }

        return collect($value)
            ->map(fn(mixed $item, mixed $key): array => [
                'label' => (string) $key,
                'value' => (string) $item,
            ])
            ->values()
            ->all();
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
            'time_slot' => 'time_slot',
            default => 'date_range',
        };
    }
}
