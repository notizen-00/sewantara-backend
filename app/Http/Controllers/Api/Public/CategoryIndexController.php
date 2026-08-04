<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\PublicCategoryIndexRequest;
use App\Http\Resources\Public\Category\CategoryResource;
use App\Modules\PublicApi\Application\Category\ListPublicCategories;
use Illuminate\Http\JsonResponse;

class CategoryIndexController extends PublicReadController
{
    public function __invoke(
        PublicCategoryIndexRequest $request,
        ListPublicCategories $categories,
    ): JsonResponse {
        $paginator = $categories->execute(
            $request->validated(),
        );

        return $this->cached(
            $request,

            CategoryResource::collection(
                $paginator->getCollection(),
            )->resolve($request),

            $this->paginationMeta($paginator),
        );
    }
}
