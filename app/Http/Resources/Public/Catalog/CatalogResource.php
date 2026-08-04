<?php

namespace App\Http\Resources\Public\Catalog;

use App\Modules\PublicApi\DTO\Catalog\PublicCatalogData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicCatalogData $catalog */
        $catalog = $this->resource;

        $paginator = $catalog->products;

        return [
            'products' => ProductCardResource::collection(
                $paginator->getCollection(),
            )->resolve($request),

            'categories' => $catalog->categories
                ->map(fn($category): array => [
                    'id' => (string) (
                        $category->public_id
                        ?: $category->getKey()
                    ),

                    'slug' => (string) $category->slug,

                    'name' => (string) $category->name,

                    'productCount' => (int) (
                        $category->product_count
                        ?? 0
                    ),
                ])
                ->values()
                ->all(),

            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'hasNextPage' => $paginator->hasMorePages(),
                'hasPreviousPage' => $paginator->currentPage() > 1,
            ],

            'appliedFilters' => $catalog->appliedFilters,
        ];
    }
}
