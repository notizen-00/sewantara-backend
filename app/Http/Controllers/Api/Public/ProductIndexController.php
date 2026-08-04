<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\PublicCatalogRequest;
use App\Http\Resources\Public\Catalog\CatalogResource;
use App\Modules\PublicApi\Application\Catalog\ListPublicProducts;
use Illuminate\Http\JsonResponse;

class ProductIndexController extends PublicReadController
{
    public function __invoke(
        PublicCatalogRequest $request,
        ListPublicProducts $products,
    ): JsonResponse {
        $catalog = new CatalogResource(
            $products->execute(
                $request->validated(),
            ),
        );

        return $this->cached(
            $request,
            $catalog->resolve($request),
        );
    }
}
