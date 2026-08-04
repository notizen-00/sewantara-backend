<?php

namespace App\Modules\PublicApi\Infrastructure\Category;

use App\Models\Category;
use App\Modules\PublicApi\Domain\Category\Contracts\PublicCategoryRepositoryContract;
use App\Modules\PublicApi\DTO\Category\PublicCategoryData;
use App\Modules\PublicApi\DTO\Category\PublicCategoryQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentPublicCategoryRepository implements PublicCategoryRepositoryContract
{
    public function paginate(
        PublicCategoryQuery $query,
    ): LengthAwarePaginator {
        $builder = Category::query()
            ->publiclyVisible()
            ->select([
                'id',
                'public_id',
                'parent_id',
                'name',
                'slug',
                'description',
                'image_path',
                'sort_order',
                'is_active',
            ])
            ->when(
                $query->search !== null
                    && $query->search !== '',
                fn(Builder $builder): Builder => $builder
                    ->where(function (Builder $search) use ($query): void {
                        $term = '%' . $query->search . '%';

                        $search
                            ->where('name', 'ilike', $term)
                            ->orWhere('slug', 'ilike', $term)
                            ->orWhere('description', 'ilike', $term);
                    }),
            )
            ->when(
                $query->onlyParents,
                fn(Builder $builder): Builder => $builder
                    ->whereNull('parent_id'),
            )
            ->when(
                $query->parentSlug !== null,
                fn(Builder $builder): Builder => $builder
                    ->whereHas(
                        'parent',
                        fn(Builder $parent): Builder => $parent
                            ->where('slug', $query->parentSlug)
                            ->publiclyVisible(),
                    ),
            )
            ->with([
                'parent:id,public_id,slug,name',
            ])
            ->when(
                $query->withChildren,
                fn(Builder $builder): Builder => $builder
                    ->with([
                        'children' => fn(Builder $children): Builder => $children
                            ->publiclyVisible()
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ]),
            )
            ->when(
                $query->withProductCount,
                fn(Builder $builder): Builder => $builder
                    ->withCount([
                        'products as product_count' => fn(Builder $products): Builder => $products
                            ->publiclyVisible(),
                    ]),
            )
            ->orderBy('sort_order')
            ->orderBy('name');

        $paginator = $builder->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(
                    fn(Category $category): PublicCategoryData => $this->toData(
                        $category,
                        $query->withChildren,
                    ),
                ),
        );

        return $paginator;
    }

    private function toData(
        Category $category,
        bool $withChildren,
    ): PublicCategoryData {
        $children = [];

        if ($withChildren && $category->relationLoaded('children')) {
            $children = $category->children
                ->map(
                    fn(Category $child): PublicCategoryData => $this->toData(
                        $child,
                        false,
                    ),
                )
                ->values()
                ->all();
        }

        return new PublicCategoryData(
            id: (string) (
                $category->public_id
                ?: $category->getKey()
            ),

            slug: (string) $category->slug,

            name: (string) $category->name,

            description: (string) (
                $category->description
                ?? ''
            ),

            parentId: $category->parent !== null
                ? (string) (
                    $category->parent->public_id
                    ?: $category->parent->getKey()
                )
                : null,

            parentSlug: $category->parent?->slug,

            imageUrl: $category->image_url,

            sortOrder: (int) $category->sort_order,

            productCount: (int) (
                $category->product_count
                ?? 0
            ),

            children: $children,
        );
    }
}
