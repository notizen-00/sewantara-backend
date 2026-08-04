<?php

namespace App\Modules\PublicApi\Application\Catalog;

use App\Modules\PublicApi\Domain\Catalog\Contracts\PublicProductRepositoryContract;
use App\Modules\PublicApi\DTO\Catalog\PublicCatalogData;
use App\Modules\PublicApi\DTO\Catalog\PublicCatalogQuery;

class ListPublicProducts
{
    public function __construct(
        private readonly PublicProductRepositoryContract $repository,
    ) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(array $attributes): PublicCatalogData
    {
        $query = PublicCatalogQuery::fromArray($attributes);

        return new PublicCatalogData(
            products: $this->repository->paginate($query),
            categories: $this->repository->categories(),
            appliedFilters: $query->filters(),
        );
    }
}
