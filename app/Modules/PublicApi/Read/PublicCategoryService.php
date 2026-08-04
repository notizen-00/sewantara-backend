<?php

namespace App\Modules\PublicApi\Read;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicCategoryService
{
    public function all(?int $limit = null): Collection
    {
        return Category::query()
            ->select([
                'id',
                'tenant_id',
                'public_id',
                'parent_id',
                'name',
                'slug',
                'description',
                'image_path',
                'sort_order',
            ])
            ->publiclyVisible()
            ->with([
                'parent' => fn (Builder $categories): Builder => $categories
                    ->publiclyVisible()
                    ->select(['id', 'public_id', 'name', 'slug']),
            ])
            ->withCount([
                'products as product_count' => fn (Builder $products): Builder => $products
                    ->publiclyVisible(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->when(
                $limit !== null,
                fn (Builder $categories): Builder => $categories->limit(
                    max(1, min($limit, 50)),
                ),
            )
            ->get();
    }
}
