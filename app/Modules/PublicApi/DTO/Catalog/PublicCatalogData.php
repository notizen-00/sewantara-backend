<?php

namespace App\Modules\PublicApi\DTO\Catalog;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class PublicCatalogData
{
    /**
     * @param Collection<int, mixed> $categories
     * @param array<string, mixed> $appliedFilters
     */
    public function __construct(
        public LengthAwarePaginator $products,
        public Collection $categories,
        public array $appliedFilters,
    ) {}
}
