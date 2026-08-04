<?php

namespace App\Modules\PublicApi\Read;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RentalConfiguration;
use App\Modules\PublicApi\Read\Support\PublicBookingMode;
use App\Modules\PublicApi\Read\Support\PublicBranchResolver;
use App\Modules\PublicApi\Read\Support\PublicMoney;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PublicCatalogService
{
    private ?RentalConfiguration $configuration = null;

    public function __construct(
        private readonly PublicBranchResolver $branches,
        private readonly PublicBookingMode $bookingMode,
        private readonly PublicMoney $money,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $branchId = $this->branches->resolve()?->getKey();
        $query = $this->cardQuery($branchId);

        $this->applySearch($query, $filters['q'] ?? null);
        $this->applyCategory($query, $filters['category'] ?? null);
        $this->applyPriceRange(
            $query,
            $branchId,
            $filters['min_price'] ?? null,
            $filters['max_price'] ?? null,
        );
        $this->applyBookingMode($query, $filters['booking_mode'] ?? null);
        $this->applyAvailability(
            $query,
            $branchId,
            $filters['available_from'] ?? null,
            $filters['available_until'] ?? null,
        );
        $this->applySort($query, (string) ($filters['sort'] ?? 'recommended'));

        $paginator = $query->paginate(
            perPage: (int) ($filters['per_page'] ?? 20),
            page: (int) ($filters['page'] ?? 1),
        );
        $this->decorate($paginator->getCollection());

        return $paginator;
    }

    public function featured(int $limit = 8): Collection
    {
        $query = $this->cardQuery($this->branches->resolve()?->getKey())
            ->where('products.is_featured', true);
        $this->applySort($query, 'recommended');
        $products = $query->limit(max(1, min($limit, 24)))->get();
        $this->decorate($products);

        return $products;
    }

    public function findBySlug(string $slug): ?Product
    {
        $branchId = $this->branches->resolve()?->getKey();
        $product = $this->cardQuery($branchId)
            ->where('products.slug', $slug)
            ->first();

        if (! $product instanceof Product) {
            return null;
        }

        $this->decorate(collect([$product]));
        $product->load([
            'prices' => fn (Builder $prices): Builder => $this
                ->effectivePriceQuery($prices)
                ->where(function (Builder $prices) use ($branchId): void {
                    if ($branchId !== null) {
                        $prices->where('branch_id', $branchId)
                            ->orWhereNull('branch_id');

                        return;
                    }

                    $prices->whereNull('branch_id');
                })
                ->orderBy('duration')
                ->orderBy('price'),
        ]);

        if ($branchId !== null
            && $product->prices->contains(
                fn (ProductPrice $price): bool => (int) $price->branch_id === (int) $branchId,
            )) {
            $product->setRelation(
                'prices',
                $product->prices->filter(
                    fn (ProductPrice $price): bool => (int) $price->branch_id === (int) $branchId,
                )->values(),
            );
        }

        return $product;
    }

    public function related(Product $product, int $limit = 4): Collection
    {
        $query = $this->cardQuery($this->branches->resolve()?->getKey())
            ->whereKeyNot($product->getKey());

        if ($product->category_id !== null) {
            $query->where('products.category_id', $product->category_id);
        }

        $this->applySort($query, 'recommended');
        $related = $query->limit(max(1, min($limit, 12)))->get();
        $this->decorate($related);

        return $related;
    }

    public function sitemapProducts(): Collection
    {
        return $this->cardQuery($this->branches->resolve()?->getKey())
            ->setEagerLoads([])
            ->orderBy('products.slug')
            ->get();
    }

    public function configuration(): ?RentalConfiguration
    {
        return $this->configuration ??= RentalConfiguration::query()->first();
    }

    private function cardQuery(int|string|null $branchId): Builder
    {
        $startingPrice = $this->startingPriceSubquery($branchId);
        $query = Product::query()
            ->select('products.*')
            ->selectSub($startingPrice, 'starting_price')
            ->publiclyVisible()
            ->where(function (Builder $products): void {
                $products->whereNull('products.category_id')
                    ->orWhereHas(
                        'category',
                        fn (Builder $categories): Builder => $categories->publiclyVisible(),
                    );
            })
            ->with([
                'category' => fn (Builder $categories): Builder => $categories
                    ->publiclyVisible()
                    ->select(['id', 'public_id', 'name', 'slug']),
                'images' => fn (Builder $images): Builder => $images->select([
                    'id',
                    'tenant_id',
                    'product_id',
                    'public_id',
                    'image_path',
                    'alt_text',
                    'is_primary',
                    'sort_order',
                ]),
            ]);

        $this->applyPricePresence($query, $branchId);

        return $query;
    }

    private function startingPriceSubquery(int|string|null $branchId): Builder
    {
        $prices = ProductPrice::query()
            ->select('price')
            ->whereColumn('product_prices.product_id', 'products.id')
            ->whereColumn(
                'product_prices.pricing_type',
                'products.default_pricing_type',
            );
        $this->effectivePriceQuery($prices);

        if ($branchId === null) {
            $prices->whereNull('branch_id');
        } else {
            $prices->where(function (Builder $prices) use ($branchId): void {
                $prices->where('branch_id', $branchId)->orWhereNull('branch_id');
            })->orderByRaw(
                'CASE WHEN product_prices.branch_id = ? THEN 0 ELSE 1 END',
                [$branchId],
            );
        }

        return $prices->orderBy('price')->limit(1);
    }

    private function effectivePriceQuery(Builder $prices): Builder
    {
        return $prices
            ->where('is_active', true)
            ->where(function (Builder $prices): void {
                $prices->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function (Builder $prices): void {
                $prices->whereNull('end_at')->orWhere('end_at', '>', now());
            });
    }

    private function applyPricePresence(Builder $products, int|string|null $branchId): void
    {
        $products->where(function (Builder $products) use ($branchId): void {
            if ($branchId !== null) {
                $products->whereHas('prices', function (Builder $prices) use ($branchId): void {
                    $this->effectivePriceQuery($prices)
                        ->where('branch_id', $branchId)
                        ->whereColumn(
                            'product_prices.pricing_type',
                            'products.default_pricing_type',
                        );
                })->orWhere(function (Builder $fallback) use ($branchId): void {
                    $fallback->whereDoesntHave('prices', function (Builder $prices) use ($branchId): void {
                        $this->effectivePriceQuery($prices)
                            ->where('branch_id', $branchId)
                            ->whereColumn(
                                'product_prices.pricing_type',
                                'products.default_pricing_type',
                            );
                    })->whereHas('prices', function (Builder $prices): void {
                        $this->effectivePriceQuery($prices)
                            ->whereNull('branch_id')
                            ->whereColumn(
                                'product_prices.pricing_type',
                                'products.default_pricing_type',
                            );
                    });
                });

                return;
            }

            $products->whereHas('prices', function (Builder $prices): void {
                $this->effectivePriceQuery($prices)
                    ->whereNull('branch_id')
                    ->whereColumn(
                        'product_prices.pricing_type',
                        'products.default_pricing_type',
                    );
            });
        });
    }

    private function applySearch(Builder $products, mixed $search): void
    {
        if (! is_string($search) || trim($search) === '') {
            return;
        }

        $pattern = '%'.mb_strtolower(trim($search)).'%';
        $products->where(function (Builder $products) use ($pattern): void {
            foreach (['name', 'brand', 'model', 'description'] as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $products->{$method}(
                    "LOWER(COALESCE(products.{$column}, '')) LIKE ?",
                    [$pattern],
                );
            }
        });
    }

    private function applyCategory(Builder $products, mixed $category): void
    {
        if (is_string($category) && $category !== '') {
            $products->whereHas(
                'category',
                fn (Builder $categories): Builder => $categories
                    ->publiclyVisible()
                    ->where('slug', $category),
            );
        }
    }

    private function applyPriceRange(
        Builder $products,
        int|string|null $branchId,
        mixed $minimum,
        mixed $maximum,
    ): void {
        if (! is_int($minimum) && ! is_int($maximum)) {
            return;
        }

        $currency = (string) app('currentTenant')->currency;
        $products->where(function (Builder $products) use (
            $branchId,
            $minimum,
            $maximum,
            $currency,
        ): void {
            if ($branchId !== null) {
                $products->whereHas('prices', function (Builder $prices) use (
                    $branchId,
                    $minimum,
                    $maximum,
                    $currency,
                ): void {
                    $this->effectivePriceQuery($prices)
                        ->where('branch_id', $branchId)
                        ->whereColumn(
                            'product_prices.pricing_type',
                            'products.default_pricing_type',
                        );
                    $this->priceBounds($prices, $minimum, $maximum, $currency);
                })->orWhere(function (Builder $fallback) use (
                    $branchId,
                    $minimum,
                    $maximum,
                    $currency,
                ): void {
                    $fallback->whereDoesntHave('prices', function (Builder $prices) use ($branchId): void {
                        $this->effectivePriceQuery($prices)
                            ->where('branch_id', $branchId)
                            ->whereColumn(
                                'product_prices.pricing_type',
                                'products.default_pricing_type',
                            );
                    })->whereHas('prices', function (Builder $prices) use (
                        $minimum,
                        $maximum,
                        $currency,
                    ): void {
                        $this->effectivePriceQuery($prices)
                            ->whereNull('branch_id')
                            ->whereColumn(
                                'product_prices.pricing_type',
                                'products.default_pricing_type',
                            );
                        $this->priceBounds($prices, $minimum, $maximum, $currency);
                    });
                });

                return;
            }

            $products->whereHas('prices', function (Builder $prices) use (
                $minimum,
                $maximum,
                $currency,
            ): void {
                $this->effectivePriceQuery($prices)
                    ->whereNull('branch_id')
                    ->whereColumn(
                        'product_prices.pricing_type',
                        'products.default_pricing_type',
                    );
                $this->priceBounds($prices, $minimum, $maximum, $currency);
            });
        });
    }

    private function priceBounds(
        Builder $prices,
        mixed $minimum,
        mixed $maximum,
        string $currency,
    ): void {
        if (is_int($minimum)) {
            $prices->where('price', '>=', $this->money->majorAmount($minimum, $currency));
        }

        if (is_int($maximum)) {
            $prices->where('price', '<=', $this->money->majorAmount($maximum, $currency));
        }
    }

    private function applyBookingMode(Builder $products, mixed $mode): void
    {
        if (! is_string($mode) || $mode === '') {
            return;
        }

        if ($mode === 'serialized_unit') {
            $products->where('products.inventory_type', 'serialized');

            return;
        }

        if ($mode === 'quantity_only') {
            $products->where('products.inventory_type', 'quantity');

            return;
        }

        if ($mode !== $this->bookingMode->resolve($this->configuration())) {
            $products->whereRaw('1 = 0');
        }
    }

    private function applyAvailability(
        Builder $products,
        int|string|null $branchId,
        mixed $from,
        mixed $until,
    ): void {
        if ($branchId === null || ! is_string($from) || ! is_string($until)) {
            return;
        }

        $timezone = (string) app('currentTenant')->timezone;
        $startsAt = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $endsAt = CarbonImmutable::parse($until, $timezone)->startOfDay()->utc();
        $now = now()->utc();
        $serialized = <<<'SQL'
(
    SELECT COUNT(*)
    FROM product_units AS pu
    WHERE pu.product_id = products.id
      AND pu.tenant_id = products.tenant_id
      AND pu.branch_id = ?
      AND pu.deleted_at IS NULL
      AND pu.status IN ('available', 'reserved')
      AND NOT EXISTS (
          SELECT 1
          FROM booking_unit_allocations AS bua
          WHERE bua.product_unit_id = pu.id
            AND bua.tenant_id = products.tenant_id
            AND bua.status IN ('reserved', 'checked_out')
            AND bua.start_at < ?
            AND bua.end_at > ?
      )
)
SQL;
        $pooled = <<<'SQL'
(
    SELECT COALESCE(SUM(
        quantity_total - quantity_reserved - quantity_rented
        - quantity_maintenance - quantity_damaged - quantity_lost
    ), 0)
    FROM inventory_stocks AS inventory
    WHERE inventory.product_id = products.id
      AND inventory.tenant_id = products.tenant_id
      AND inventory.branch_id = ?
)
SQL;
        $holds = <<<'SQL'
(
    SELECT COALESCE(SUM(sh.quantity), 0)
    FROM stock_holds AS sh
    WHERE sh.product_id = products.id
      AND sh.tenant_id = products.tenant_id
      AND sh.branch_id = ?
      AND sh.status = 'active'
      AND sh.booking_id IS NULL
      AND sh.expires_at > ?
      AND sh.starts_at < ?
      AND sh.ends_at > ?
)
SQL;
        $products->where(function (Builder $products) use (
            $serialized,
            $pooled,
            $holds,
            $branchId,
            $startsAt,
            $endsAt,
            $now,
        ): void {
            $products->where(function (Builder $serializedProducts) use (
                $serialized,
                $holds,
                $branchId,
                $startsAt,
                $endsAt,
                $now,
            ): void {
                $serializedProducts
                    ->where('products.inventory_type', 'serialized')
                    ->whereRaw(
                        "{$serialized} - {$holds} >= 1",
                        [
                            $branchId,
                            $endsAt,
                            $startsAt,
                            $branchId,
                            $now,
                            $endsAt,
                            $startsAt,
                        ],
                    );
            })->orWhere(function (Builder $pooledProducts) use (
                $pooled,
                $holds,
                $branchId,
                $startsAt,
                $endsAt,
                $now,
            ): void {
                $pooledProducts
                    ->where('products.inventory_type', 'quantity')
                    ->whereRaw(
                        "{$pooled} - {$holds} >= 1",
                        [
                            $branchId,
                            $branchId,
                            $now,
                            $endsAt,
                            $startsAt,
                        ],
                    );
            });
        });
    }

    private function applySort(Builder $products, string $sort): void
    {
        match ($sort) {
            'newest' => $products
                ->orderByDesc('products.published_at')
                ->orderByDesc('products.id'),
            'price_asc' => $products
                ->orderBy('starting_price')
                ->orderBy('products.id'),
            'price_desc' => $products
                ->orderByDesc('starting_price')
                ->orderBy('products.id'),
            'name_asc' => $products
                ->orderByRaw('LOWER(products.name) ASC')
                ->orderBy('products.id'),
            'popular' => $this->applyPopularity($products)
                ->orderByDesc('popularity_score')
                ->orderBy('products.id'),
            default => $this->applyPopularity($products)
                ->orderByDesc('products.is_featured')
                ->orderByDesc('popularity_score')
                ->orderByDesc('products.published_at')
                ->orderBy('products.id'),
        };
    }

    private function applyPopularity(Builder $products): Builder
    {
        $popularity = DB::table('booking_items as popularity_items')
            ->selectRaw('COALESCE(SUM(popularity_items.quantity), 0)')
            ->join(
                'bookings as popularity_bookings',
                'popularity_bookings.id',
                '=',
                'popularity_items.booking_id',
            )
            ->whereColumn('popularity_items.product_id', 'products.id')
            ->whereColumn('popularity_items.tenant_id', 'products.tenant_id')
            ->whereNull('popularity_bookings.deleted_at')
            ->whereNotIn('popularity_bookings.status', ['draft', 'cancelled']);

        return $products->selectSub($popularity, 'popularity_score');
    }

    private function decorate(Collection $products): void
    {
        $mode = $this->bookingMode->resolve($this->configuration());

        $products->each(function (Product $product) use ($mode): void {
            $product->setAttribute('public_booking_mode', $mode);
            $product->setAttribute(
                'public_inventory_type',
                $this->bookingMode->inventoryType($product->inventory_type),
            );
            $product->setAttribute(
                'public_realtime_availability',
                (bool) ($this->configuration()?->realtime_availability ?? false),
            );
        });
    }
}
