<?php

namespace App\Modules\PublicApi\Application\Category;

use App\Modules\PublicApi\Domain\Category\Contracts\PublicCategoryRepositoryContract;
use App\Modules\PublicApi\DTO\Category\PublicCategoryQuery;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPublicCategories
{
    public function __construct(
        private readonly PublicCategoryRepositoryContract $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        array $attributes,
    ): LengthAwarePaginator {
        return $this->repository->paginate(
            PublicCategoryQuery::fromArray($attributes),
        );
    }
}
