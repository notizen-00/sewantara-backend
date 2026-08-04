<?php

namespace App\Modules\PublicApi\Domain\Category\Contracts;

use App\Modules\PublicApi\DTO\Category\PublicCategoryQuery;
use Illuminate\Pagination\LengthAwarePaginator;

interface PublicCategoryRepositoryContract
{
    public function paginate(
        PublicCategoryQuery $query,
    ): LengthAwarePaginator;
}
