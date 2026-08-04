<?php

namespace App\Modules\PublicApi\DTO\Catalog;

readonly class PublicCatalogQuery
{
    public function __construct(
        public ?string $search,
        public ?string $category,
        public ?int $minimumPrice,
        public ?int $maximumPrice,
        public ?string $bookingMode,
        public ?bool $featured,
        public string $sort,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            search: isset($attributes['search'])
                ? trim((string) $attributes['search'])
                : null,

            category: isset($attributes['category'])
                ? strtolower(trim((string) $attributes['category']))
                : null,

            minimumPrice: isset($attributes['min_price'])
                ? (int) $attributes['min_price']
                : null,

            maximumPrice: isset($attributes['max_price'])
                ? (int) $attributes['max_price']
                : null,

            bookingMode: isset($attributes['booking_mode'])
                ? (string) $attributes['booking_mode']
                : null,

            featured: array_key_exists('featured', $attributes)
                ? (bool) $attributes['featured']
                : null,

            sort: (string) ($attributes['sort'] ?? 'recommended'),

            page: max(1, (int) ($attributes['page'] ?? 1)),

            perPage: min(
                50,
                max(1, (int) ($attributes['per_page'] ?? 20)),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter([
            'search' => $this->search,
            'category' => $this->category,
            'minPrice' => $this->minimumPrice,
            'maxPrice' => $this->maximumPrice,
            'bookingMode' => $this->bookingMode,
            'featured' => $this->featured,
            'sort' => $this->sort,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
