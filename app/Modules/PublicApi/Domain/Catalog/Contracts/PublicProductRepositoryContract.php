<?php

namespace App\Modules\PublicApi\Domain\Catalog\Contracts;

use App\Modules\PublicApi\DTO\Catalog\PublicCatalogQuery;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PublicProductRepositoryContract
{
    public function paginate(
        PublicCatalogQuery $query,
    ): LengthAwarePaginator;

    public function categories(): Collection;
}
