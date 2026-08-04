<?php

namespace App\Http\Resources\Public\Category;

use App\Modules\PublicApi\DTO\Category\PublicCategoryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicCategoryData $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,

            'parent' => $category->parentId !== null
                ? [
                    'id' => $category->parentId,
                    'slug' => $category->parentSlug,
                ]
                : null,

            'image' => [
                'url' => $category->imageUrl ?? '',
                'alt' => $category->name,
            ],

            'sortOrder' => $category->sortOrder,

            'productCount' => $category->productCount,

            'children' => collect($category->children)
                ->map(
                    fn(PublicCategoryData $child): array => [
                        'id' => $child->id,
                        'slug' => $child->slug,
                        'name' => $child->name,
                        'description' => $child->description,

                        'image' => [
                            'url' => $child->imageUrl ?? '',
                            'alt' => $child->name,
                        ],

                        'sortOrder' => $child->sortOrder,

                        'productCount' => $child->productCount,
                    ],
                )
                ->values()
                ->all(),
        ];
    }
}
